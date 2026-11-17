<?php

declare(strict_types=1);

use Buzlook\AdminSession;
use Buzlook\Controller\AdminController;
use Buzlook\Controller\ApiController;
use Buzlook\Controller\HomeController;
use Buzlook\Controller\LabelController;
use Buzlook\Database;
use Buzlook\FreezerSession;
use Buzlook\PhotoStorage;
use Buzlook\RateLimiter;
use Buzlook\Repository\ItemRepository;
use Buzlook\Repository\TagRepository;
use Buzlook\Router;
use Buzlook\Translator;
use Buzlook\View;
use Buzlook\VoiceStorage;

// Let the PHP built-in server serve static files (assets, uploads) directly.
if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($requestedFile) && $requestedFile !== __FILE__ && !str_ends_with($requestedFile, '.php')) {
        return false;
    }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Buzlook\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$config = require __DIR__ . '/config.php';

$pdo = Database::connect($config['database_path']);
$translator = new Translator(__DIR__ . '/lang', (string) $config['default_language'], $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
$view = new View(__DIR__ . '/templates', $translator);

$tags = new TagRepository($pdo);
$items = new ItemRepository($pdo);
$session = new FreezerSession($tags, $config['session_cookie_lifetime']);
$photos = new PhotoStorage($config['uploads_path'], $config['max_photo_bytes']);
$voices = new VoiceStorage($config['uploads_path'], $config['max_voice_bytes']);
$rateLimiter = new RateLimiter($pdo);
$clientKey = RateLimiter::clientKey($_SERVER, $config['client_ip_header']);

$home = new HomeController($session, $view);
$labels = new LabelController($tags, $items, $session, $photos, $voices, $rateLimiter, $view, $config, $clientKey);
$api = new ApiController($tags, $config, $rateLimiter, $clientKey);
$adminSession = new AdminSession($config['api_token_hash'] ?? null);
$admin = new AdminController($pdo, $tags, $photos, $voices, $rateLimiter, $adminSession, $view, $config, $clientKey);

$router = new Router();
$router->get('/', [$home, 'show']);
$router->post('/logout', [$labels, 'logout']);
$router->get('/export/labels', [$labels, 'exportLabels']);

// Admin dashboard & actions (token cookie).
$router->get('/admin', [$admin, 'show']);
$router->post('/admin/login', [$admin, 'login']);
$router->post('/admin/logout', [$admin, 'logout']);
$router->post('/admin/tags/batch', [$admin, 'generateBatch']);
$router->post('/admin/tags/bulk', [$admin, 'bulkAction']);
$router->post('/admin/tags/inspect-action', [$admin, 'inspectAction']);
$router->post('/admin/maintenance/purge-inactive', [$admin, 'purgeInactive']);
$router->post('/admin/maintenance/clean-orphans', [$admin, 'cleanOrphans']);
$router->post('/admin/rate-limits/clear', [$admin, 'clearRateLimits']);
$router->get('/admin/backup/database', [$admin, 'downloadBackup']);

// Admin API (bearer token).
$router->post('/api/v1/tags/generate', [$api, 'generate']);
$router->post('/api/v1/tags/register', [$api, 'register']);
$router->post('/api/v1/tags/unregister', [$api, 'unregister']);
$router->post('/api/v1/tags', [$api, 'create']);

// Scanned labels. The code alone is the URL: https://host/{code}
$router->get('/{code}', [$labels, 'show']);
$router->post('/{code}/freezer', [$labels, 'setupFreezer']);
$router->post('/{code}/add', [$labels, 'add']);
$router->get('/{code}/edit', [$labels, 'edit']);
$router->post('/{code}/edit', [$labels, 'update']);
$router->post('/{code}/consume', [$labels, 'consume']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $view);
