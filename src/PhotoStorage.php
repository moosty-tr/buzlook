<?php

declare(strict_types=1);

namespace Buzlook;

use InvalidArgumentException;

/**
 * Validates and stores uploaded item photos.
 */
final class PhotoStorage
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @param array<string, mixed>|null $file An entry of $_FILES.
     * @return string|null The stored file name (relative to the uploads directory), or null when no photo was sent.
     * @throws InvalidArgumentException When the upload is invalid; the message is a translation key (photo.*).
     */
    public function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('photo.too_large');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('photo.upload_failed');
        }
        if ($file['size'] > $this->maxBytes) {
            throw new InvalidArgumentException('photo.too_large');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
            throw new InvalidArgumentException('photo.invalid_type');
        }

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_MIME_TYPES[$mimeType];
        if (!move_uploaded_file($file['tmp_name'], $this->directory . '/' . $fileName)) {
            throw new InvalidArgumentException('photo.save_failed');
        }

        return $fileName;
    }

    public function delete(?string $fileName): void
    {
        if ($fileName === null || $fileName === '') {
            return;
        }

        $path = $this->directory . '/' . basename($fileName);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
