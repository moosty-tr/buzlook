<?php

declare(strict_types=1);

/**
 * Creates (or replaces) the admin API token.
 *
 * Writes the token's SHA-256 hash into config.local.php and prints the token ONCE.
 * Run it with the PHP of the environment you are configuring, e.g. on the XAMPP copy:
 *   D:\Xampp\php\php.exe D:\Xampp\htdocs\bin\create-api-token.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$localFile = __DIR__ . '/../config.local.php';
$local = is_file($localFile) ? (array) require $localFile : [];

$token = bin2hex(random_bytes(24));
$local['api_token_hash'] = hash('sha256', $token);

$export = "<?php\n\nreturn " . var_export($local, true) . ";\n";
if (file_put_contents($localFile, $export, LOCK_EX) === false) {
    fwrite(STDERR, "Could not write {$localFile}\n");
    exit(1);
}

echo "API token (shown only once, store it safely):\n{$token}\n";
