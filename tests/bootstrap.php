<?php

declare(strict_types=1);

$root = dirname(__DIR__);

// Composer autoload (preferred — used when running `composer install` first).
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
}

// Custom autoloader (fallback so tests work even without composer for non-PHPUnit code).
require $root . '/src/Core/Autoloader.php';
\FurEver\Core\Autoloader::register($root . '/src');

if (!session_id()) {
    @session_start();
}
