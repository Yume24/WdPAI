<?php

namespace FurEver\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function check(?string $candidate): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';
        return is_string($candidate)
            && $expected !== ''
            && hash_equals($expected, $candidate);
    }

    public static function fromRequest(): ?string
    {
        if (!empty($_POST['_csrf'])) {
            return (string) $_POST['_csrf'];
        }
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'X-CSRF-Token') === 0) {
                return (string) $value;
            }
        }
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        return null;
    }
}
