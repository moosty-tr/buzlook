<?php

declare(strict_types=1);

namespace Buzlook\Controller;

use Buzlook\CodeGenerator;
use Buzlook\FreezerSession;
use Buzlook\PhotoStorage;
use Buzlook\PublicUrl;
use Buzlook\RateLimiter;
use Buzlook\Repository\ItemRepository;
use Buzlook\Repository\TagRepository;
use Buzlook\View;
use Buzlook\VoiceStorage;
use DateTimeImmutable;
use InvalidArgumentException;
use PDOException;

/**
 * Handles everything a scanned label can do: /{code} and its actions.
 *
 * What a label does depends on its state:
 *  - unknown / unregistered / disabled -> denied (and counted by the rate limiter)
 *  - registered, untyped, nobody logged in -> can be set up as a freezer label
 *  - registered, untyped, logged in -> becomes a food label of that freezer (on first save)
 *  - freezer label -> logs in and lists the freezer content
 *  - food label -> add item / edit / re-label / mark consumed
 */
final class LabelController
{
    private const MAX_NAME_LENGTH = 100;
    private const MAX_QUANTITY_LENGTH = 50;
    private const MAX_NOTE_LENGTH = 500;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly TagRepository $tags,
        private readonly ItemRepository $items,
        private readonly FreezerSession $session,
        private readonly PhotoStorage $photos,
        private readonly VoiceStorage $voices,
        private readonly RateLimiter $rateLimiter,
        private readonly View $view,
        private readonly array $config,
        private readonly string $clientKey,
    ) {
    }

    public function show(string $code): void
    {
        $tag = $this->findActiveTag($code);
        if ($tag === null) {
            return;
        }

        $freezer = $this->session->currentFreezer();
        $this->recordScan($tag, $freezer);

        if ($tag['type'] === 'freezer') {
            $this->session->start($tag['code']);
            $this->view->render('freezer', [
                'title' => $tag['name'],
                'freezer' => $tag,
                'items' => $this->items->findActiveByFreezerId((int) $tag['id']),
                'uploadsUrl' => $this->config['uploads_url'],
            ]);
            return;
        }

        if ($tag['type'] === null) {
            if ($freezer === null) {
                $this->view->render('freezer_setup', ['title' => $this->view->t('setup.title'), 'tag' => $tag, 'input' => ['name' => ''], 'errors' => []]);
                return;
            }
            $this->renderAddForm($tag, $freezer, $this->emptyInput(), []);
            return;
        }

        // Food label.
        $freezer = $this->requireOwningFreezer($tag, $freezer);
        if ($freezer === null) {
            return;
        }

        $activeItem = $this->items->findActiveByTagId((int) $tag['id']);
        if ($activeItem === null) {
            $this->renderAddForm($tag, $freezer, $this->emptyInput(), []);
            return;
        }

        $this->view->render('tag_consume', [
            'title' => $this->view->t('consume.title'),
            'tag' => $tag,
            'freezer' => $freezer,
            'item' => $activeItem,
            'uploadsUrl' => $this->config['uploads_url'],
        ]);
    }

    /**
     * Gives a registered, untyped label to a new freezer and logs the browser in to it.
     */
    public function setupFreezer(string $code): void
    {
        $tag = $this->findActiveTag($code);
        if ($tag === null) {
            return;
        }
        if ($tag['type'] !== null) {
            $this->redirect('/' . $tag['code']);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            http_response_code(422);
            $this->view->render('freezer_setup', [
                'title' => $this->view->t('setup.title'),
                'tag' => $tag,
                'input' => ['name' => $name],
                'errors' => ['name' => $this->view->t('setup.error_name', ['max' => self::MAX_NAME_LENGTH])],
            ]);
            return;
        }

        if ($this->tags->claimAsFreezer((int) $tag['id'], $name)) {
            $this->session->start($tag['code']);
        }
        $this->redirect('/' . $tag['code']);
    }

    public function add(string $code): void
    {
        $tag = $this->findActiveTag($code);
        if ($tag === null) {
            return;
        }

        $freezer = $this->session->currentFreezer();
        if ($freezer === null) {
            $this->renderScanFreezerFirst();
            return;
        }
        if ($tag['type'] !== null && $tag['type'] !== 'food') {
            $this->redirect('/' . $tag['code']);
        }
        if ($tag['type'] === 'food') {
            if ($this->requireOwningFreezer($tag, $freezer) === null) {
                return;
            }
            if ($this->items->findActiveByTagId((int) $tag['id']) !== null) {
                $this->redirect('/' . $tag['code']);
            }
        }

        $input = $this->readInput();
        $errors = $this->validateInput($input);

        $photoFileName = null;
        $voiceFileName = null;
        if ($errors === []) {
            try {
                $photoFileName = $this->photos->store($_FILES['photo'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors['photo'] = $this->view->t($exception->getMessage());
            }
        }
        if ($errors === []) {
            try {
                $voiceFileName = $this->voices->store($_FILES['voice'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors['voice'] = $this->view->t($exception->getMessage());
                $this->photos->delete($photoFileName);
            }
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderAddForm($tag, $freezer, $input, $errors);
            return;
        }

        // First use of an untyped label: it becomes a food label of the logged-in freezer.
        if ($tag['type'] === null && !$this->tags->claimAsFood((int) $tag['id'], (int) $freezer['id'])) {
            $this->photos->delete($photoFileName);
            $this->voices->delete($voiceFileName);
            $this->redirect('/' . $tag['code']);
        }

        $this->items->create(
            (int) $freezer['id'],
            (int) $tag['id'],
            $input['name'],
            $input['frozen_date'],
            $photoFileName,
            $input['quantity'] !== '' ? $input['quantity'] : null,
            $input['note'] !== '' ? $input['note'] : null,
            $voiceFileName,
        );

        $this->redirect('/' . $freezer['code']);
    }

    public function edit(string $code): void
    {
        $context = $this->resolveEditContext($code);
        if ($context === null) {
            return;
        }

        [$tag, $freezer, $item] = $context;
        $this->renderEditForm($tag, $freezer, $item, [
            'name' => (string) $item['name'],
            'frozen_date' => (string) $item['frozen_date'],
            'quantity' => (string) ($item['quantity'] ?? ''),
            'note' => (string) ($item['note'] ?? ''),
            'new_code' => '',
        ], []);
    }

    public function update(string $code): void
    {
        $context = $this->resolveEditContext($code);
        if ($context === null) {
            return;
        }

        [$tag, $freezer, $item] = $context;
        $input = $this->readInput();
        $input['new_code'] = $this->normalizeCode((string) ($_POST['new_code'] ?? ''));
        $errors = $this->validateInput($input);

        // Re-label: the item moves to another registered, unused label.
        $targetTag = $tag;
        if ($errors === [] && $input['new_code'] !== '' && $input['new_code'] !== $tag['code']) {
            $targetTag = $this->resolveNewLabel($input['new_code'], $freezer);
            if ($targetTag === null) {
                $targetTag = $tag;
                $errors['new_code'] = $this->view->t('edit.error_new_label');
            }
        }

        $newPhoto = null;
        $newVoice = null;
        if ($errors === []) {
            try {
                $newPhoto = $this->photos->store($_FILES['photo'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors['photo'] = $this->view->t($exception->getMessage());
            }
        }
        if ($errors === []) {
            try {
                $newVoice = $this->voices->store($_FILES['voice'] ?? null);
            } catch (InvalidArgumentException $exception) {
                $errors['voice'] = $this->view->t($exception->getMessage());
                $this->photos->delete($newPhoto);
            }
        }

        $removePhoto = !empty($_POST['remove_photo']);
        $removeVoice = !empty($_POST['remove_voice']);
        $photoPath = $newPhoto ?? ($removePhoto ? null : $item['photo_path']);
        $voicePath = $newVoice ?? ($removeVoice ? null : $item['voice_path']);

        if ($errors === []) {
            try {
                if ($targetTag['type'] === null && !$this->tags->claimAsFood((int) $targetTag['id'], (int) $freezer['id'])) {
                    throw new InvalidArgumentException('label taken');
                }
                $this->items->update(
                    (int) $item['id'],
                    (int) $targetTag['id'],
                    $input['name'],
                    $input['frozen_date'],
                    $input['quantity'] !== '' ? $input['quantity'] : null,
                    $input['note'] !== '' ? $input['note'] : null,
                    $photoPath,
                    $voicePath,
                );
            } catch (InvalidArgumentException | PDOException) {
                // Someone else took the new label in the meantime.
                $errors['new_code'] = $this->view->t('edit.error_new_label');
                $this->photos->delete($newPhoto);
                $this->voices->delete($newVoice);
            }
        } else {
            $this->photos->delete($newPhoto);
            $this->voices->delete($newVoice);
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->renderEditForm($tag, $freezer, $item, $input, $errors);
            return;
        }

        if ($newPhoto !== null || $removePhoto) {
            $this->photos->delete($item['photo_path']);
        }
        if ($newVoice !== null || $removeVoice) {
            $this->voices->delete($item['voice_path']);
        }

        $this->redirect('/' . $freezer['code']);
    }

    public function consume(string $code): void
    {
        $tag = $this->findActiveTag($code);
        if ($tag === null) {
            return;
        }

        $freezer = $this->requireOwningFreezer($tag, $this->session->currentFreezer());
        if ($freezer === null) {
            return;
        }

        $activeItem = $this->items->findActiveByTagId((int) $tag['id']);
        if ($activeItem !== null) {
            $this->items->markConsumed((int) $activeItem['id']);
            $this->photos->delete($activeItem['photo_path']);
            $this->voices->delete($activeItem['voice_path']);
        }

        $this->redirect('/' . $freezer['code']);
    }

    public function logout(): void
    {
        $this->session->end();
        $this->redirect('/');
    }

    /**
     * GET /export/labels: the label codes and URLs of the logged-in freezer (the freezer label and all its
     * food labels). The browser draws the QR codes and builds the ZIP of PNG images.
     */
    public function exportLabels(): void
    {
        $freezer = $this->session->currentFreezer();

        http_response_code($freezer === null ? 403 : 200);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');

        if ($freezer === null) {
            echo json_encode(['error' => 'Not logged in to a freezer.']);
            return;
        }

        $labels = [['type' => 'freezer', 'code' => $freezer['code'], 'url' => PublicUrl::forCode($this->config, $freezer['code'])]];
        foreach ($this->tags->findFoodByFreezerId((int) $freezer['id']) as $food) {
            $labels[] = ['type' => 'food', 'code' => $food['code'], 'url' => PublicUrl::forCode($this->config, $food['code'])];
        }

        echo json_encode(['labels' => $labels], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Looks up an enabled label. Renders a page and returns null when the client is rate limited or the label is
     * unknown / unregistered / disabled (which counts as a failure).
     *
     * @return array<string, mixed>|null
     */
    private function findActiveTag(string $code): ?array
    {
        if ($this->renderIfBlocked()) {
            return null;
        }

        $tag = $this->tags->findByCode($code);
        if ($tag === null || (int) $tag['enabled'] !== 1) {
            $this->recordUnknownLabel();
            http_response_code(404);
            $this->view->render('message', [
                'title' => $this->view->t('error.label_invalid.title'),
                'message' => $this->view->t('error.label_invalid.message'),
            ]);
            return null;
        }

        return $tag;
    }

    private function renderIfBlocked(): bool
    {
        $retryAfter = $this->rateLimiter->retryAfter($this->scanLimitKey());
        if ($retryAfter <= 0) {
            return false;
        }

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        $this->view->render('message', [
            'title' => $this->view->t('error.rate_limited.title'),
            'message' => $this->view->t('error.rate_limited.message', ['minutes' => (int) ceil($retryAfter / 60)]),
        ]);

        return true;
    }

    private function recordUnknownLabel(): void
    {
        $limits = $this->config['rate_limits']['scan'];
        $this->rateLimiter->recordFailure(
            $this->scanLimitKey(),
            (int) $limits['max_failures'],
            (int) $limits['window'],
            (int) $limits['block'],
        );
    }

    private function scanLimitKey(): string
    {
        return 'scan:' . $this->clientKey;
    }

    /**
     * Stores "last scanned now" on the scanned label and, when the scan happens inside a freezer session that
     * the label belongs to, on the freezer label too (so an active freezer is never considered abandoned).
     *
     * @param array<string, mixed> $tag
     * @param array<string, mixed>|null $freezer
     */
    private function recordScan(array $tag, ?array $freezer): void
    {
        $ids = [(int) $tag['id']];
        if ($freezer !== null && ($tag['type'] === null || (int) $tag['freezer_id'] === (int) $freezer['id'])) {
            $ids[] = (int) $freezer['id'];
        }

        $this->tags->touchLastScan($ids);
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}|null
     *         tag, freezer and active item; null after rendering / redirecting.
     */
    private function resolveEditContext(string $code): ?array
    {
        $tag = $this->findActiveTag($code);
        if ($tag === null) {
            return null;
        }
        if ($tag['type'] !== 'food') {
            $this->redirect('/' . $tag['code']);
        }

        $freezer = $this->requireOwningFreezer($tag, $this->session->currentFreezer());
        if ($freezer === null) {
            return null;
        }

        $item = $this->items->findActiveByTagId((int) $tag['id']);
        if ($item === null) {
            $this->redirect('/' . $tag['code']);
        }

        return [$tag, $freezer, $item];
    }

    /**
     * A label an item may move to: registered and either still untyped or a free food label of this freezer.
     *
     * @param array<string, mixed> $freezer
     * @return array<string, mixed>|null
     */
    private function resolveNewLabel(string $code, array $freezer): ?array
    {
        if (!CodeGenerator::isValid($code) || $this->rateLimiter->retryAfter($this->scanLimitKey()) > 0) {
            return null;
        }

        $tag = $this->tags->findByCode($code);
        if ($tag === null || (int) $tag['enabled'] !== 1) {
            $this->recordUnknownLabel();
            return null;
        }
        if ($tag['type'] === null) {
            return $tag;
        }
        if ($tag['type'] === 'food'
            && (int) $tag['freezer_id'] === (int) $freezer['id']
            && $this->items->findActiveByTagId((int) $tag['id']) === null) {
            return $tag;
        }

        return null;
    }

    /**
     * Accepts a bare code or a pasted label URL and returns the code.
     */
    private function normalizeCode(string $value): string
    {
        $value = trim($value);
        if (str_contains($value, '/')) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
            $value = $segments === [] ? '' : $segments[0];
        }

        return $value;
    }

    /**
     * Checks that a food label belongs to the logged-in freezer. Renders an error page and returns null otherwise.
     *
     * @param array<string, mixed> $tag
     * @param array<string, mixed>|null $freezer
     * @return array<string, mixed>|null The freezer.
     */
    private function requireOwningFreezer(array $tag, ?array $freezer): ?array
    {
        if ($freezer === null) {
            $this->renderScanFreezerFirst();
            return null;
        }

        if ((int) $tag['freezer_id'] !== (int) $freezer['id']) {
            http_response_code(403);
            $this->view->render('message', [
                'title' => $this->view->t('error.wrong_freezer.title'),
                'message' => $this->view->t('error.wrong_freezer.message'),
                'back_url' => '/' . $freezer['code'],
                'back_label' => $this->view->t('error.back_to', ['name' => $freezer['name']]),
            ]);
            return null;
        }

        return $freezer;
    }

    private function renderScanFreezerFirst(): void
    {
        http_response_code(403);
        $this->view->render('message', [
            'title' => $this->view->t('error.freezer_not_scanned.title'),
            'message' => $this->view->t('error.freezer_not_scanned.message'),
        ]);
    }

    private function hasUpload(string $field): bool
    {
        return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @return array<string, string>
     */
    private function emptyInput(): array
    {
        return ['name' => '', 'frozen_date' => date('Y-m-d'), 'quantity' => '', 'note' => '', 'new_code' => ''];
    }

    /**
     * @return array<string, string>
     */
    private function readInput(): array
    {
        return [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'frozen_date' => trim((string) ($_POST['frozen_date'] ?? '')),
            'quantity' => trim((string) ($_POST['quantity'] ?? '')),
            'note' => trim((string) ($_POST['note'] ?? '')),
            'new_code' => '',
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        if ($input['name'] === '' || mb_strlen($input['name']) > self::MAX_NAME_LENGTH) {
            $errors['name'] = $this->view->t('add.error_name', ['max' => self::MAX_NAME_LENGTH]);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $input['frozen_date']);
        if ($date === false || $date->format('Y-m-d') !== $input['frozen_date']) {
            $errors['frozen_date'] = $this->view->t('add.error_date');
        }

        if (mb_strlen($input['quantity']) > self::MAX_QUANTITY_LENGTH) {
            $errors['quantity'] = $this->view->t('add.error_quantity', ['max' => self::MAX_QUANTITY_LENGTH]);
        }
        if (mb_strlen($input['note']) > self::MAX_NOTE_LENGTH) {
            $errors['note'] = $this->view->t('add.error_note', ['max' => self::MAX_NOTE_LENGTH]);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $tag
     * @param array<string, mixed> $freezer
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function renderAddForm(array $tag, array $freezer, array $input, array $errors): void
    {
        $this->view->render('tag_add', [
            'title' => $this->view->t('add.title'),
            'tag' => $tag,
            'freezer' => $freezer,
            'input' => $input,
            'errors' => $errors,
            'voiceMaxSeconds' => (int) $this->config['voice_max_seconds'],
            'voiceMaxBytes' => (int) $this->config['max_voice_bytes'],
        ]);
    }

    /**
     * @param array<string, mixed> $tag
     * @param array<string, mixed> $freezer
     * @param array<string, mixed> $item
     * @param array<string, string> $input
     * @param array<string, string> $errors
     */
    private function renderEditForm(array $tag, array $freezer, array $item, array $input, array $errors): void
    {
        $this->view->render('tag_edit', [
            'title' => $this->view->t('edit.title'),
            'tag' => $tag,
            'freezer' => $freezer,
            'item' => $item,
            'input' => $input,
            'errors' => $errors,
            'uploadsUrl' => $this->config['uploads_url'],
            'voiceMaxSeconds' => (int) $this->config['voice_max_seconds'],
            'voiceMaxBytes' => (int) $this->config['max_voice_bytes'],
        ]);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
