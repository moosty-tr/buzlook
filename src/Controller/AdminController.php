<?php

declare(strict_types=1);

namespace Buzlook\Controller;

use Buzlook\AdminSession;
use Buzlook\CodeGenerator;
use Buzlook\PhotoStorage;
use Buzlook\PublicUrl;
use Buzlook\RateLimiter;
use Buzlook\Repository\TagRepository;
use Buzlook\View;
use Buzlook\VoiceStorage;
use PDO;

/**
 * Handles the /admin dashboard and its management actions:
 * - Batch code generation (registered or unregistered) for ZIP / A4 PDF grid / CSV export
 * - Bulk register / unregister from pasted codes or uploaded CSV
 * - Single label lookup, enable/disable, and reset to untyped
 * - Inactivity report (not scanned in last N days, default 730) & purge of abandoned freezers
 * - Orphan upload cleanup, rate-limit block clearing, and SQLite database backup download
 */
final class AdminController
{
    private const DEFAULT_INACTIVE_DAYS = 730;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly TagRepository $tags,
        private readonly PhotoStorage $photos,
        private readonly VoiceStorage $voices,
        private readonly RateLimiter $rateLimiter,
        private readonly AdminSession $adminSession,
        private readonly View $view,
        private readonly array $config,
        private readonly string $clientKey,
    ) {
    }

    public function show(): void
    {
        if (!$this->adminSession->isAuthenticated()) {
            $this->renderLogin();
            return;
        }

        $days = isset($_GET['days']) ? max(1, min(36500, (int) $_GET['days'])) : self::DEFAULT_INACTIVE_DAYS;
        $inspectCode = $this->normalizeCode((string) ($_GET['inspect'] ?? ''));
        $inspectedTag = null;
        if ($inspectCode !== '' && CodeGenerator::isValid($inspectCode)) {
            $inspectedTag = $this->tags->inspectByCode($inspectCode);
        }

        $this->view->render('admin', [
            'title' => $this->view->t('admin.title'),
            'csrfToken' => $this->adminSession->csrfToken(),
            'stats' => $this->tags->summaryStats(),
            'storage' => $this->storageStats(),
            'inactivity' => $this->tags->inactivityReport($days),
            'inspectCode' => $inspectCode,
            'inspectedTag' => $inspectedTag,
            'inspectedUrl' => $inspectCode !== '' ? PublicUrl::forCode($this->config, $inspectCode) : '',
            'rateBlocks' => $this->rateLimiter->activeBlocks(),
            'defaultLength' => (int) $this->config['code_length'],
            'maxBatch' => (int) $this->config['api_max_batch'],
            'flash' => (string) ($_GET['flash'] ?? ''),
            'flashCount' => (int) ($_GET['count'] ?? 0),
        ]);
    }

    public function login(): void
    {
        if (!$this->adminSession->isConfigured()) {
            $this->renderLogin($this->view->t('admin.not_configured'));
            return;
        }

        $limits = $this->config['rate_limits']['api'];
        $limitKey = 'api:' . $this->clientKey;
        $retryAfter = $this->rateLimiter->retryAfter($limitKey);
        if ($retryAfter > 0) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
            $this->renderLogin($this->view->t('error.rate_limited.message', ['minutes' => (int) ceil($retryAfter / 60)]));
            return;
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        if (!$this->adminSession->verifyToken($token)) {
            $this->rateLimiter->recordFailure(
                $limitKey,
                (int) $limits['max_failures'],
                (int) $limits['window'],
                (int) $limits['block'],
            );
            http_response_code(401);
            $this->renderLogin($this->view->t('admin.invalid_token'));
            return;
        }

        $this->adminSession->start();
        $this->redirect('/admin');
    }

    public function logout(): void
    {
        $this->adminSession->end();
        $this->redirect('/admin');
    }

    /**
     * POST /admin/tags/batch (JSON response for the client-side ZIP / PDF / CSV exporter).
     */
    public function generateBatch(): void
    {
        if (!$this->requireJsonAuth()) {
            return;
        }

        $max = (int) $this->config['api_max_batch'];
        $count = (int) ($_POST['count'] ?? 0);
        $length = isset($_POST['length']) && $_POST['length'] !== ''
            ? (int) $_POST['length']
            : (int) $this->config['code_length'];
        $register = ($_POST['mode'] ?? 'register') === 'register';

        if ($count < 1 || $count > $max) {
            $this->json(422, ['error' => "\"count\" must be between 1 and {$max}."]);
        }
        if ($length < CodeGenerator::MIN_LENGTH || $length > CodeGenerator::MAX_LENGTH) {
            $this->json(422, ['error' => '"length" must be between ' . CodeGenerator::MIN_LENGTH . ' and ' . CodeGenerator::MAX_LENGTH . '.']);
        }

        $codes = $this->tags->generateUniqueCodes($count, $length);
        if ($register) {
            $this->tags->register($codes);
        }

        $base = PublicUrl::base($this->config);
        $tags = array_map(
            static fn (string $code): array => ['code' => $code, 'url' => $base . '/' . $code],
            $codes,
        );

        $this->json(200, [
            'count' => count($tags),
            'registered' => $register,
            'tags' => $tags,
        ]);
    }

    /**
     * POST /admin/tags/bulk (JSON response): registers or unregisters a list of codes from textarea or CSV.
     */
    public function bulkAction(): void
    {
        if (!$this->requireJsonAuth()) {
            return;
        }

        $action = (string) ($_POST['action'] ?? 'register');
        $rawText = (string) ($_POST['codes_text'] ?? '');

        if (isset($_FILES['csv_file']) && ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploaded = file_get_contents((string) $_FILES['csv_file']['tmp_name']);
            if (is_string($uploaded)) {
                $rawText .= "\n" . $uploaded;
            }
        }

        [$codes, $invalid] = $this->extractCodesFromText($rawText);
        $max = (int) $this->config['api_max_batch'];

        if ($codes === []) {
            $this->json(422, ['error' => $this->view->t('admin.bulk_empty'), 'invalid' => array_slice($invalid, 0, 20)]);
        }
        if (count($codes) > $max) {
            $this->json(422, ['error' => "\"codes\" must contain at most {$max} codes."]);
        }

        if ($action === 'unregister') {
            $result = $this->tags->unregister($codes);
            $result['invalid'] = array_slice($invalid, 0, 20);
            $this->json(200, ['action' => 'unregister', 'result' => $result]);
        }

        $result = $this->tags->register($codes);
        $result['invalid'] = array_slice($invalid, 0, 20);
        $this->json(200, ['action' => 'register', 'result' => $result]);
    }

    /**
     * POST /admin/tags/inspect-action: enable, disable, register, or reset a single inspected label.
     */
    public function inspectAction(): void
    {
        $this->requirePostAuth();

        $code = $this->normalizeCode((string) ($_POST['code'] ?? ''));
        $operation = (string) ($_POST['operation'] ?? '');
        if (!CodeGenerator::isValid($code)) {
            $this->redirect('/admin');
        }

        if ($operation === 'register') {
            $this->tags->register([$code]);
            $this->redirect('/admin?inspect=' . urlencode($code) . '&flash=updated');
        }

        $tag = $this->tags->findByCode($code);
        if ($tag === null) {
            $this->redirect('/admin?inspect=' . urlencode($code));
        }

        $tagId = (int) $tag['id'];
        if ($operation === 'enable') {
            $this->tags->setEnabled($tagId, true);
        } elseif ($operation === 'disable') {
            $this->tags->setEnabled($tagId, false);
        } elseif ($operation === 'reset') {
            foreach ($this->tags->resetToUntyped($tagId) as $file) {
                $this->photos->delete($file);
                $this->voices->delete($file);
            }
        }

        $this->redirect('/admin?inspect=' . urlencode($code) . '&flash=updated');
    }

    /**
     * POST /admin/maintenance/purge-inactive: deletes freezers inactive for > N days and their media files.
     */
    public function purgeInactive(): void
    {
        $this->requirePostAuth();

        $days = isset($_POST['days']) ? max(1, min(36500, (int) $_POST['days'])) : self::DEFAULT_INACTIVE_DAYS;
        $purged = $this->tags->purgeInactiveFreezers($days);
        foreach ($purged['media_files'] as $file) {
            $this->photos->delete($file);
            $this->voices->delete($file);
        }

        $this->redirect('/admin?days=' . $days . '&flash=purged&count=' . $purged['deleted_freezers']);
    }

    /**
     * POST /admin/maintenance/clean-orphans: removes files in uploads/ that are not referenced by any item.
     */
    public function cleanOrphans(): void
    {
        $this->requirePostAuth();

        $referenced = array_flip($this->tags->allReferencedMediaFiles());
        $uploadsDir = (string) $this->config['uploads_path'];
        $deleted = 0;

        if (is_dir($uploadsDir)) {
            foreach (scandir($uploadsDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }
                $fullPath = $uploadsDir . '/' . $entry;
                if (is_file($fullPath) && !isset($referenced[$entry])) {
                    if (@unlink($fullPath)) {
                        $deleted++;
                    }
                }
            }
        }

        $this->redirect('/admin?flash=orphans_cleaned&count=' . $deleted);
    }

    /**
     * POST /admin/rate-limits/clear: removes all current rate-limit blocks.
     */
    public function clearRateLimits(): void
    {
        $this->requirePostAuth();

        $cleared = $this->rateLimiter->clearAll();
        $this->redirect('/admin?flash=rate_limits_cleared&count=' . $cleared);
    }

    /**
     * GET /admin/backup/database: streams a consistent SQLite database backup file.
     */
    public function downloadBackup(): void
    {
        if (!$this->adminSession->isAuthenticated()) {
            $this->redirect('/admin');
        }

        $dbPath = (string) $this->config['database_path'];
        $tmpBackup = sys_get_temp_dir() . '/buzlook-backup-' . bin2hex(random_bytes(8)) . '.sqlite';

        try {
            $quoted = $this->pdo->quote($tmpBackup);
            $this->pdo->exec("VACUUM INTO {$quoted}");
        } catch (\Throwable) {
            $this->pdo->exec('PRAGMA wal_checkpoint(FULL)');
            copy($dbPath, $tmpBackup);
        }

        $downloadName = 'buzlook-backup-' . date('Y-m-d') . '.sqlite';
        header('Content-Type: application/vnd.sqlite3');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string) filesize($tmpBackup));
        header('Cache-Control: no-store');
        readfile($tmpBackup);
        @unlink($tmpBackup);
        exit;
    }

    private function renderLogin(?string $error = null): void
    {
        $this->view->render('admin_login', [
            'title' => $this->view->t('admin.login_title'),
            'configured' => $this->adminSession->isConfigured(),
            'error' => $error,
        ]);
    }

    private function requirePostAuth(): void
    {
        if (!$this->adminSession->isAuthenticated() || !$this->adminSession->verifyCsrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $this->redirect('/admin');
        }
    }

    private function requireJsonAuth(): bool
    {
        if (!$this->adminSession->isAuthenticated() || !$this->adminSession->verifyCsrf($_POST['csrf_token'] ?? null)) {
            $this->json(403, ['error' => 'Unauthorized or invalid CSRF token.']);
        }

        return true;
    }

    /**
     * @return array{uploads_files: int, uploads_bytes: int, db_bytes: int, orphan_files: int}
     */
    private function storageStats(): array
    {
        $uploadsDir = (string) $this->config['uploads_path'];
        $referenced = array_flip($this->tags->allReferencedMediaFiles());
        $files = 0;
        $bytes = 0;
        $orphans = 0;

        if (is_dir($uploadsDir)) {
            foreach (scandir($uploadsDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }
                $path = $uploadsDir . '/' . $entry;
                if (is_file($path)) {
                    $files++;
                    $bytes += (int) filesize($path);
                    if (!isset($referenced[$entry])) {
                        $orphans++;
                    }
                }
            }
        }

        $dbPath = (string) $this->config['database_path'];
        $dbBytes = is_file($dbPath) ? (int) filesize($dbPath) : 0;

        return [
            'uploads_files' => $files,
            'uploads_bytes' => $bytes,
            'db_bytes' => $dbBytes,
            'orphan_files' => $orphans,
        ];
    }

    /**
     * Parses pasted lines or CSV rows (such as "url,code" or bare codes) into unique valid Base58 codes.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function extractCodesFromText(string $rawText): array
    {
        $valid = [];
        $invalid = [];

        $lines = preg_split('/[\r\n]+/', $rawText) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // If it looks like a 2-column CSV line ("https://host/7Kq9Xz,7Kq9Xz" or "url,code"):
            if (str_contains($line, ',') || str_contains($line, ';')) {
                $parts = str_getcsv($line, str_contains($line, ';') ? ';' : ',');
                $parts = array_values(array_filter(array_map(static fn (?string $v): string => trim((string) $v), $parts), static fn (string $v): bool => $v !== ''));
                if ($parts === []) {
                    continue;
                }
                if ( count($parts) >= 2 && strtolower($parts[0]) === 'url' && strtolower($parts[1]) === 'code') {
                    continue;
                }
                $candidate = count($parts) >= 2 ? $this->normalizeCode($parts[1]) : $this->normalizeCode($parts[0]);
                if (CodeGenerator::isValid($candidate)) {
                    $valid[$candidate] = true;
                } else {
                    $fallback = $this->normalizeCode($parts[0]);
                    if (CodeGenerator::isValid($fallback)) {
                        $valid[$fallback] = true;
                    } else {
                        $invalid[] = $line;
                    }
                }
                continue;
            }

            foreach (preg_split('/\s+/', $line) ?: [] as $token) {
                $token = trim($token);
                if ($token === '' || strtolower($token) === 'code' || strtolower($token) === 'url') {
                    continue;
                }
                $code = $this->normalizeCode($token);
                if (CodeGenerator::isValid($code)) {
                    $valid[$code] = true;
                } else {
                    $invalid[] = $token;
                }
            }
        }

        return [array_map('strval', array_keys($valid)), $invalid];
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (str_contains($value, '/')) {
            $path = (string) parse_url($value, PHP_URL_PATH);
            $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
            $value = $segments === [] ? '' : $segments[0];
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(int $status, array $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }
}
