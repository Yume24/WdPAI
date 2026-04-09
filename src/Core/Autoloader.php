<?php

namespace FurEver\Core;

final class Autoloader
{
    public static function register(string $baseDir, string $namespacePrefix = 'FurEver\\'): void
    {
        spl_autoload_register(function (string $class) use ($baseDir, $namespacePrefix) {
            if (strncmp($class, $namespacePrefix, strlen($namespacePrefix)) !== 0) {
                return;
            }
            $relative = substr($class, strlen($namespacePrefix));
            $file = $baseDir . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
