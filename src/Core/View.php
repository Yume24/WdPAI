<?php

namespace FurEver\Core;

final class View
{
    public static function render(string $template, array $variables = []): string
    {
        $variables = self::withDefaults($variables);
        $base = dirname(__DIR__, 2) . '/public/views/';
        $path = $base . ltrim($template, '/') . '.html';

        if (!is_file($path)) {
            $path = $base . '404.html';
        }

        extract($variables, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function output(string $template, array $variables = []): void
    {
        echo self::render($template, $variables);
    }

    private static function withDefaults(array $variables): array
    {
        $defaults = [
            'currentUser'     => null,
            'currentRole'     => Session::role(),
            'currentUsername' => Session::username(),
            'csrfToken'       => Csrf::token(),
            'flashes'         => Flash::pull(),
            'isLoggedIn'      => Session::userId() !== null,
        ];

        return array_merge($defaults, $variables);
    }
}
