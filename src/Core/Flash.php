<?php

namespace FurEver\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function pull(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }
}
