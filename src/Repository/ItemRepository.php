<?php

declare(strict_types=1);

namespace Buzlook\Repository;

use PDO;

final class ItemRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActiveByFreezerId(int $freezerId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT items.*, tags.code AS tag_code
             FROM items
             JOIN tags ON tags.id = items.tag_id
             WHERE items.freezer_id = :freezer_id AND items.status = 'active'
             ORDER BY items.frozen_date ASC, items.id ASC"
        );
        $statement->execute(['freezer_id' => $freezerId]);

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByTagId(int $tagId): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM items WHERE tag_id = :tag_id AND status = 'active'");
        $statement->execute(['tag_id' => $tagId]);

        return $statement->fetch() ?: null;
    }

    public function create(
        int $freezerId,
        int $tagId,
        string $name,
        string $frozenDate,
        ?string $photoPath,
        ?string $quantity,
        ?string $note,
        ?string $voicePath,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO items (freezer_id, tag_id, name, frozen_date, photo_path, quantity, note, voice_path)
             VALUES (:freezer_id, :tag_id, :name, :frozen_date, :photo_path, :quantity, :note, :voice_path)'
        );
        $statement->execute([
            'freezer_id' => $freezerId,
            'tag_id' => $tagId,
            'name' => $name,
            'frozen_date' => $frozenDate,
            'photo_path' => $photoPath,
            'quantity' => $quantity,
            'note' => $note,
            'voice_path' => $voicePath,
        ]);
    }

    /**
     * Overwrites the editable fields of an active item. $tagId may point to a different label (re-label).
     */
    public function update(
        int $itemId,
        int $tagId,
        string $name,
        string $frozenDate,
        ?string $quantity,
        ?string $note,
        ?string $photoPath,
        ?string $voicePath,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE items
             SET tag_id = :tag_id, name = :name, frozen_date = :frozen_date, quantity = :quantity,
                 note = :note, photo_path = :photo_path, voice_path = :voice_path
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $itemId,
            'tag_id' => $tagId,
            'name' => $name,
            'frozen_date' => $frozenDate,
            'quantity' => $quantity,
            'note' => $note,
            'photo_path' => $photoPath,
            'voice_path' => $voicePath,
        ]);
    }

    /**
     * Marks an item as consumed and detaches its photo and voice note references.
     */
    public function markConsumed(int $itemId): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE items
             SET status = 'consumed', consumed_at = CURRENT_TIMESTAMP, photo_path = NULL, voice_path = NULL
             WHERE id = :id"
        );
        $statement->execute(['id' => $itemId]);
    }
}
