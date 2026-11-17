<?php

declare(strict_types=1);

namespace Buzlook;

use PDO;

/**
 * Creates the SQLite PDO connection and makes sure the schema exists.
 */
final class Database
{
    public static function connect(string $databasePath): PDO
    {
        $directory = dirname($databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');

        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        $steps = [
            2 => static function (PDO $pdo): void {
                if (self::tableExists($pdo, 'fridges')) {
                    self::archiveLegacySchema($pdo);
                }
                self::createSchemaV2($pdo);
            },
            3 => static function (PDO $pdo): void {
                self::upgradeToV3($pdo);
            },
        ];

        foreach ($steps as $target => $step) {
            if ($version >= $target) {
                continue;
            }

            $pdo->beginTransaction();
            try {
                $step($pdo);
                $pdo->exec('PRAGMA user_version = ' . $target);
                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
        }
    }

    /**
     * v3: last scan time per label, quantity / note / voice note per item, rate limiting.
     */
    private static function upgradeToV3(PDO $pdo): void
    {
        // UTC "YYYY-MM-DD HH:MM:SS" of the latest scan (a single value, no history). NULL = never scanned;
        // use COALESCE(last_scan_at, created_at) when looking for abandoned labels.
        $pdo->exec('ALTER TABLE tags ADD COLUMN last_scan_at TEXT');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tags_last_scan ON tags(last_scan_at)');

        $pdo->exec('ALTER TABLE items ADD COLUMN quantity TEXT');
        $pdo->exec('ALTER TABLE items ADD COLUMN note TEXT');
        $pdo->exec('ALTER TABLE items ADD COLUMN voice_path TEXT');

        // One row per client key (e.g. "scan:<ip>"): failures inside the current window and an optional block.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                key TEXT PRIMARY KEY,
                failures INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL,
                blocked_until INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
        $statement->execute(['name' => $table]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * The first prototype used UUID based fridges/tags/items tables. They are kept (renamed) so
     * no data is lost, but the application no longer reads them.
     */
    private static function archiveLegacySchema(PDO $pdo): void
    {
        $pdo->exec('DROP INDEX IF EXISTS idx_items_one_active_per_tag');
        $pdo->exec('DROP INDEX IF EXISTS idx_items_fridge_status');
        $pdo->exec('ALTER TABLE items RENAME TO legacy_items');
        $pdo->exec('ALTER TABLE tags RENAME TO legacy_tags');
        $pdo->exec('ALTER TABLE fridges RENAME TO legacy_fridges');
    }

    private static function createSchemaV2(PDO $pdo): void
    {
        // One table for every physical label. A label is:
        //  - unregistered: it has no row at all (scanning it is denied),
        //  - registered but untyped: type IS NULL (first scan decides what it becomes),
        //  - a freezer label (type 'freezer', has a name) or a food label (type 'food', belongs to a freezer).
        // enabled = 0 "unregisters" a label without losing its history.
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                type TEXT CHECK (type IN ('freezer', 'food')),
                name TEXT,
                freezer_id INTEGER REFERENCES tags(id) ON DELETE CASCADE,
                enabled INTEGER NOT NULL DEFAULT 1 CHECK (enabled IN (0, 1)),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK (
                    (type IS NULL AND name IS NULL AND freezer_id IS NULL)
                    OR (type = 'freezer' AND name IS NOT NULL AND freezer_id IS NULL)
                    OR (type = 'food' AND freezer_id IS NOT NULL)
                )
            )"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                freezer_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
                tag_id INTEGER NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
                name TEXT NOT NULL,
                frozen_date TEXT NOT NULL,
                photo_path TEXT,
                status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'consumed')),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                consumed_at TEXT
            )"
        );

        // A tag can hold at most one active item at a time.
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_items_one_active_per_tag
             ON items(tag_id) WHERE status = 'active'"
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS idx_items_freezer_status ON items(freezer_id, status)'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tags_freezer ON tags(freezer_id)');
    }
}
