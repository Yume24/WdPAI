<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Core/Autoloader.php';
\FurEver\Core\Autoloader::register(__DIR__ . '/src');

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

\FurEver\Core\Env::load(__DIR__ . '/.env');

if (is_file(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

if (\FurEver\Core\Env::bool('APP_FORCE_HTTPS', false) && !\FurEver\Core\Env::isHttps()) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

if (\FurEver\Core\Env::isHttps()) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

\FurEver\Core\Session::start();

set_error_handler(static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(static function (\Throwable $e): void {
    error_log('[FurEver] uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    (new \FurEver\Controllers\ErrorController())->render500($e);
});

require_once __DIR__ . '/Routing.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

Routing::dispatch($method, $path);
