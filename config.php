<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * Environment specific values (API token hash, public URL ...) belong in config.local.php,
 * which is git-ignored and never touched by deploy.ps1. Its keys override the defaults below.
 */
$config = [
    'database_path' => __DIR__ . '/data/buzlook.sqlite',
    'uploads_path' => __DIR__ . '/uploads',
    'uploads_url' => '/uploads',
    // Hard server-side limit for the (already client-compressed) photo.
    'max_photo_bytes' => 200 * 1024,
    'session_cookie_lifetime' => 60 * 60 * 24 * 365,

    // Optional voice note per item: speech quality is enough. The recorder stops at voice_max_seconds;
    // max_voice_bytes is the hard server-side limit (php.ini upload_max_filesize must be at least this big).
    'voice_max_seconds' => 60,
    'max_voice_bytes' => 1024 * 1024,

    // Rate limiting of failures per client IP: after max_failures inside window seconds the client is
    // blocked for block seconds. "scan" counts unknown/unregistered labels, "api" counts bad API tokens.
    // Overriding this key in config.local.php replaces the whole array.
    'rate_limits' => [
        'scan' => ['max_failures' => 20, 'window' => 600, 'block' => 900],
        'api' => ['max_failures' => 10, 'window' => 900, 'block' => 900],
    ],
    // Behind a reverse proxy set this to e.g. 'HTTP_X_FORWARDED_FOR' (the last entry is used), otherwise
    // every visitor looks like the proxy. Leave null when Apache is reached directly.
    'client_ip_header' => null,

    // Language used when the browser's preferred languages match none of the files in lang/ (e.g. 'en', 'tr').
    'default_language' => 'en',

    // Length of newly generated tag codes (Base58). Can be raised any time; old codes keep working.
    'code_length' => 6,
    // Largest number of tags one API call may create.
    'api_max_batch' => 5000,
    // Base URL printed into QR codes, e.g. 'https://buzlook.example'. null = derive from the request.
    'public_base_url' => null,
    // SHA-256 hash of the admin API token. null = API disabled. Create one with bin/create-api-token.php.
    'api_token_hash' => null,
];

$localFile = __DIR__ . '/config.local.php';
if (is_file($localFile)) {
    $config = array_replace($config, (array) require $localFile);
}

return $config;
