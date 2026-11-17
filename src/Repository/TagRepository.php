<?php

declare(strict_types=1);

namespace Buzlook\Repository;

use PDO;

/**
 * Storage for every physical label (freezer labels and food labels share one table).
 */
final class TagRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCode(string $code): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tags WHERE code = :code');
        $statement->execute(['code' => $code]);

        return $statement->fetch() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tags WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param list<string> $codes
     * @return list<string> The subset of $codes that already exist.
     */
    public function existingCodes(array $codes): array
    {
        $existing = [];
        foreach (array_chunk($codes, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $statement = $this->pdo->prepare("SELECT code FROM tags WHERE code IN ($placeholders)");
            $statement->execute($chunk);
            foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $code) {
                $existing[] = (string) $code;
            }
        }

        return $existing;
    }

    /**
     * Registers codes: unknown codes are created, disabled ones are enabled again.
     *
     * @param list<string> $codes
     * @return array{created: list<string>, enabled: list<string>, already_registered: list<string>}
     */
    public function register(array $codes): array
    {
        $result = ['created' => [], 'enabled' => [], 'already_registered' => []];

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('INSERT INTO tags (code) VALUES (:code)');
            $enable = $this->pdo->prepare('UPDATE tags SET enabled = 1 WHERE code = :code');

            foreach ($codes as $code) {
                $tag = $this->findByCode($code);
                if ($tag === null) {
                    $insert->execute(['code' => $code]);
                    $result['created'][] = $code;
                } elseif ((int) $tag['enabled'] === 0) {
                    $enable->execute(['code' => $code]);
                    $result['enabled'][] = $code;
                } else {
                    $result['already_registered'][] = $code;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $result;
    }

    /**
     * Disables codes (history is kept). Unknown codes are reported back.
     *
     * @param list<string> $codes
     * @return array{unregistered: list<string>, already_unregistered: list<string>, not_found: list<string>}
     */
    public function unregister(array $codes): array
    {
        $result = ['unregistered' => [], 'already_unregistered' => [], 'not_found' => []];

        $this->pdo->beginTransaction();
        try {
            $disable = $this->pdo->prepare('UPDATE tags SET enabled = 0 WHERE code = :code');

            foreach ($codes as $code) {
                $tag = $this->findByCode($code);
                if ($tag === null) {
                    $result['not_found'][] = $code;
                } elseif ((int) $tag['enabled'] === 0) {
                    $result['already_unregistered'][] = $code;
                } else {
                    $disable->execute(['code' => $code]);
                    $result['unregistered'][] = $code;
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return $result;
    }

    /**
     * Turns a registered, still untyped label into a freezer label.
     *
     * @return bool False when the label was no longer untyped (e.g. claimed by someone else meanwhile).
     */
    public function claimAsFreezer(int $tagId, string $name): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE tags SET type = \'freezer\', name = :name
             WHERE id = :id AND type IS NULL AND enabled = 1'
        );
        $statement->execute(['id' => $tagId, 'name' => $name]);

        return $statement->rowCount() === 1;
    }

    /**
     * Turns a registered, still untyped label into a food label of the given freezer.
     *
     * @return bool False when the label was no longer untyped.
     */
    public function claimAsFood(int $tagId, int $freezerId): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE tags SET type = \'food\', freezer_id = :freezer_id
             WHERE id = :id AND type IS NULL AND enabled = 1'
        );
        $statement->execute(['id' => $tagId, 'freezer_id' => $freezerId]);

        return $statement->rowCount() === 1;
    }

    /**
     * Remembers "now" (UTC) as the last scan time. Only the latest value is kept, no history.
     *
     * @param list<int> $tagIds
     */
    public function touchLastScan(array $tagIds): void
    {
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
        if ($tagIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $this->pdo->prepare("UPDATE tags SET last_scan_at = datetime('now') WHERE id IN ($placeholders)")
            ->execute($tagIds);
    }

    /**
     * Every enabled food label of a freezer (used or free), oldest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findFoodByFreezerId(int $freezerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM tags WHERE freezer_id = :freezer_id AND type = 'food' AND enabled = 1 ORDER BY id ASC"
        );
        $statement->execute(['freezer_id' => $freezerId]);

        return $statement->fetchAll();
    }
}