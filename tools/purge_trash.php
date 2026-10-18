<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/../app/';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) require_once $file;
    });
}

try {
    $purgedCount = (new \App\Models\Workflow())->purgeExpiredTrash();
    fwrite(STDOUT, sprintf("Trash cleanup complete: %d expired report process(es) permanently deleted.\n", $purgedCount));
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Trash cleanup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
