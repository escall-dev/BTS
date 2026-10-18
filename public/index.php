<?php

$autoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../app/';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

session_start();

use App\Core\Router;

$router = new Router();

require_once __DIR__ . '/../routes/web.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/BTS';

if (stripos($uri, $basePath) === 0) {
    $uri = substr($uri, strlen($basePath));
}

if (stripos($uri, '/public') === 0) {
    $uri = substr($uri, strlen('/public'));
}

if ($uri === '' || $uri === '/') {
    $uri = '/';
}

if (!empty($_SESSION['user'])) {
    $lastTrashCleanup = (int) ($_SESSION['_bts_trash_cleanup_checked_at'] ?? 0);
    if ($lastTrashCleanup < time() - 3600) {
        $_SESSION['_bts_trash_cleanup_checked_at'] = time();
        try {
            (new \App\Models\Workflow())->purgeExpiredTrash();
        } catch (\Throwable $exception) {
            error_log('BTS Trash retention cleanup failed: ' . $exception->getMessage());
        }
    }
}

$router->dispatch($uri, $_SERVER['REQUEST_METHOD']);
