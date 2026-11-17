<?php

declare(strict_types=1);

namespace Buzlook;

use InvalidArgumentException;

/**
 * Validates and stores an optional voice note (speech quality is enough, so files are small).
 *
 * The length limit (60 s by default) is enforced by the browser's recorder / file check; here the file size
 * is the hard limit, which also bounds the length for any sensible bitrate.
 */
final class VoiceStorage
{
    private const ALLOWED_MIME_TYPES = [
        'audio/webm' => 'webm',
        'video/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'application/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'video/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/mpeg' => 'mp3',
        'audio/aac' => 'aac',
        'audio/x-hx-aac-adts' => 'aac',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/3gpp' => '3gp',
        'video/3gpp' => '3gp',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @param array<string, mixed>|null $file An entry of $_FILES.
     * @return string|null The stored file name, or null when no voice note was sent.
     * @throws InvalidArgumentException With a translation key (voice.*) when the upload is invalid.
     */
    public function store(?array $file): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('voice.too_large');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('voice.upload_failed');
        }
        if ($file['size'] > $this->maxBytes) {
            throw new InvalidArgumentException('voice.too_large');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new InvalidArgumentException('voice.invalid_type');
        }

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . self::ALLOWED_MIME_TYPES[$mimeType];
        if (!move_uploaded_file($file['tmp_name'], $this->directory . '/' . $fileName)) {
            throw new InvalidArgumentException('voice.upload_failed');
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
