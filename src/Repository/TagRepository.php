<?php

declare(strict_types=1);

namespace Buzlook\Repository;

use Buzlook\CodeGenerator;
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
     * Generates $count unique Base58 codes of $length characters that do not yet exist in the database.
     *
     * @return list<string>
     */
    public function generateUniqueCodes(int $count, int $length): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $candidates = [];
            while (count($candidates) + count($codes) < $count) {
                $candidates[CodeGenerator::generate($length)] = true;
            }
            $candidates = array_diff(array_keys($candidates), array_keys($codes));
            $taken = array_flip($this->existingCodes($candidates));
            foreach ($candidates as $candidate) {
                if (!isset($taken[$candidate])) {
                    $codes[$candidate] = true;
                }
            }
        }

        return array_map('strval', array_keys($codes));
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

    /**
     * High-level counts for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function summaryStats(): array
    {
        $tagRow = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END), 0) AS total_enabled,
                COALESCE(SUM(CASE WHEN enabled = 1 AND type IS NULL THEN 1 ELSE 0 END), 0) AS untyped,
                COALESCE(SUM(CASE WHEN enabled = 1 AND type = 'freezer' THEN 1 ELSE 0 END), 0) AS freezers,
                COALESCE(SUM(CASE WHEN enabled = 1 AND type = 'food' THEN 1 ELSE 0 END), 0) AS food,
                COALESCE(SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END), 0) AS disabled
             FROM tags"
        )->fetch() ?: [];

        $itemRow = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_items,
                COALESCE(SUM(CASE WHEN status = 'consumed' THEN 1 ELSE 0 END), 0) AS consumed_items
             FROM items"
        )->fetch() ?: [];

        return [
            'total_enabled' => (int) ($tagRow['total_enabled'] ?? 0),
            'untyped' => (int) ($tagRow['untyped'] ?? 0),
            'freezers' => (int) ($tagRow['freezers'] ?? 0),
            'food' => (int) ($tagRow['food'] ?? 0),
            'disabled' => (int) ($tagRow['disabled'] ?? 0),
            'active_items' => (int) ($itemRow['active_items'] ?? 0),
            'consumed_items' => (int) ($itemRow['consumed_items'] ?? 0),
        ];
    }

    /**
     * Reports enabled labels not scanned in the last $days days (default 730 = 2 years),
     * separating unused shelf stock from abandoned freezers.
     *
     * @return array{
     *     days: int,
     *     cutoff: string,
     *     total_unscanned: int,
     *     never_scanned: int,
     *     untyped_unscanned: int,
     *     claimed_unscanned: int,
     *     inactive_freezers: list<array<string, mixed>>
     * }
     */
    public function inactivityReport(int $days): array
    {
        $days = max(1, $days);
        $cutoffStmt = $this->pdo->prepare("SELECT datetime('now', :offset)");
        $cutoffStmt->execute(['offset' => '-' . $days . ' days']);
        $cutoff = (string) $cutoffStmt->fetchColumn();

        $countsStmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN last_scan_at IS NULL OR last_scan_at < :cutoff THEN 1 ELSE 0 END), 0) AS total_unscanned,
                COALESCE(SUM(CASE WHEN last_scan_at IS NULL THEN 1 ELSE 0 END), 0) AS never_scanned,
                COALESCE(SUM(CASE WHEN type IS NULL AND (last_scan_at IS NULL OR last_scan_at < :cutoff) THEN 1 ELSE 0 END), 0) AS untyped_unscanned,
                COALESCE(SUM(CASE WHEN type IS NOT NULL AND (last_scan_at IS NULL OR last_scan_at < :cutoff) THEN 1 ELSE 0 END), 0) AS claimed_unscanned
             FROM tags
             WHERE enabled = 1"
        );
        $countsStmt->execute(['cutoff' => $cutoff]);
        $counts = $countsStmt->fetch() ?: [];

        $freezersStmt = $this->pdo->prepare(
            "SELECT
                f.*,
                (SELECT COUNT(*) FROM tags ft WHERE ft.freezer_id = f.id) AS food_tag_count,
                (SELECT COUNT(*) FROM items i WHERE i.freezer_id = f.id AND i.status = 'active') AS active_item_count
             FROM tags f
             WHERE f.type = 'freezer'
               AND f.enabled = 1
               AND COALESCE(f.last_scan_at, f.created_at) < :cutoff
               AND NOT EXISTS (
                   SELECT 1 FROM tags ft
                   WHERE ft.freezer_id = f.id
                     AND ft.last_scan_at IS NOT NULL
                     AND ft.last_scan_at >= :cutoff
               )
             ORDER BY COALESCE(f.last_scan_at, f.created_at) ASC, f.id ASC"
        );
        $freezersStmt->execute(['cutoff' => $cutoff]);

        return [
            'days' => $days,
            'cutoff' => $cutoff,
            'total_unscanned' => (int) ($counts['total_unscanned'] ?? 0),
            'never_scanned' => (int) ($counts['never_scanned'] ?? 0),
            'untyped_unscanned' => (int) ($counts['untyped_unscanned'] ?? 0),
            'claimed_unscanned' => (int) ($counts['claimed_unscanned'] ?? 0),
            'inactive_freezers' => $freezersStmt->fetchAll(),
        ];
    }

    /**
     * Deletes freezers (and via ON DELETE CASCADE their food labels and items) that have not been scanned
     * in the last $days days. Returns the media filenames that were attached to their items.
     *
     * @return array{deleted_freezers: int, deleted_food_tags: int, media_files: list<string>}
     */
    public function purgeInactiveFreezers(int $days): array
    {
        $report = $this->inactivityReport($days);
        $freezers = $report['inactive_freezers'];
        if ($freezers === []) {
            return ['deleted_freezers' => 0, 'deleted_food_tags' => 0, 'media_files' => []];
        }

        $freezerIds = array_map(static fn (array $row): int => (int) $row['id'], $freezers);
        $placeholders = implode(',', array_fill(0, count($freezerIds), '?'));

        $this->pdo->beginTransaction();
        try {
            $mediaStmt = $this->pdo->prepare(
                "SELECT photo_path, voice_path FROM items WHERE freezer_id IN ($placeholders)"
            );
            $mediaStmt->execute($freezerIds);
            $mediaFiles = [];
            foreach ($mediaStmt->fetchAll() as $row) {
                if (!empty($row['photo_path'])) {
                    $mediaFiles[] = (string) $row['photo_path'];
                }
                if (!empty($row['voice_path'])) {
                    $mediaFiles[] = (string) $row['voice_path'];
                }
            }

            $foodCountStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tags WHERE freezer_id IN ($placeholders)");
            $foodCountStmt->execute($freezerIds);
            $deletedFoodTags = (int) $foodCountStmt->fetchColumn();

            $deleteStmt = $this->pdo->prepare("DELETE FROM tags WHERE id IN ($placeholders)");
            $deleteStmt->execute($freezerIds);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'deleted_freezers' => count($freezerIds),
            'deleted_food_tags' => $deletedFoodTags,
            'media_files' => array_values(array_unique($mediaFiles)),
        ];
    }

    /**
     * Detailed lookup of a single label code for the admin inspector.
     *
     * @return array<string, mixed>|null
     */
    public function inspectByCode(string $code): ?array
    {
        $tag = $this->findByCode($code);
        if ($tag === null) {
            return null;
        }

        $id = (int) $tag['id'];
        if ($tag['type'] === 'freezer') {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tags WHERE freezer_id = :id');
            $stmt->execute(['id' => $id]);
            $tag['food_tag_count'] = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM items WHERE freezer_id = :id AND status = 'active'");
            $stmt->execute(['id' => $id]);
            $tag['active_item_count'] = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM items WHERE freezer_id = :id AND status = 'consumed'");
            $stmt->execute(['id' => $id]);
            $tag['consumed_item_count'] = (int) $stmt->fetchColumn();
        } elseif ($tag['type'] === 'food') {
            $tag['freezer'] = $tag['freezer_id'] !== null ? $this->findById((int) $tag['freezer_id']) : null;
            $stmt = $this->pdo->prepare("SELECT * FROM items WHERE tag_id = :id AND status = 'active'");
            $stmt->execute(['id' => $id]);
            $tag['active_item'] = $stmt->fetch() ?: null;
        }

        return $tag;
    }

    public function setEnabled(int $tagId, bool $enabled): void
    {
        $statement = $this->pdo->prepare('UPDATE tags SET enabled = :enabled WHERE id = :id');
        $statement->execute(['enabled' => $enabled ? 1 : 0, 'id' => $tagId]);
    }

    /**
     * Resets a label back to a fresh untyped registered label.
     * If it was a freezer, its food labels are also reset to untyped and its items are deleted.
     *
     * @return list<string> Media filenames detached by the reset.
     */
    public function resetToUntyped(int $tagId): array
    {
        $this->pdo->beginTransaction();
        try {
            $mediaStmt = $this->pdo->prepare(
                'SELECT photo_path, voice_path FROM items WHERE tag_id = :id OR freezer_id = :freezer_id'
            );
            $mediaStmt->execute(['id' => $tagId, 'freezer_id' => $tagId]);
            $mediaFiles = [];
            foreach ($mediaStmt->fetchAll() as $row) {
                if (!empty($row['photo_path'])) {
                    $mediaFiles[] = (string) $row['photo_path'];
                }
                if (!empty($row['voice_path'])) {
                    $mediaFiles[] = (string) $row['voice_path'];
                }
            }

            $this->pdo->prepare('DELETE FROM items WHERE tag_id = :id OR freezer_id = :freezer_id')
                ->execute(['id' => $tagId, 'freezer_id' => $tagId]);

            $this->pdo->prepare('UPDATE tags SET type = NULL, name = NULL, freezer_id = NULL WHERE freezer_id = :id')
                ->execute(['id' => $tagId]);

            $this->pdo->prepare('UPDATE tags SET type = NULL, name = NULL, freezer_id = NULL, enabled = 1 WHERE id = :id')
                ->execute(['id' => $tagId]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return array_values(array_unique($mediaFiles));
    }

    /**
     * All photo and voice filenames currently referenced by items.
     *
     * @return list<string>
     */
    public function allReferencedMediaFiles(): array
    {
        $rows = $this->pdo->query(
            'SELECT photo_path, voice_path FROM items WHERE photo_path IS NOT NULL OR voice_path IS NOT NULL'
        )->fetchAll();

        $files = [];
        foreach ($rows as $row) {
            if (!empty($row['photo_path'])) {
                $files[] = basename((string) $row['photo_path']);
            }
            if (!empty($row['voice_path'])) {
                $files[] = basename((string) $row['voice_path']);
            }
        }

        return array_values(array_unique($files));
    }
}
