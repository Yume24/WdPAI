<?php

namespace FurEver\Controllers;

use FurEver\Core\Csrf;
use FurEver\Core\Flash;
use FurEver\Core\Session;
use FurEver\Core\View;

abstract class AppController
{
    protected function isGet(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    protected function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function render(string $template, array $variables = []): void
    {
        View::output($template, $variables);
    }

    protected function redirect(string $path, int $status = 302): void
    {
        header('Location: ' . $path, true, $status);
        exit;
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        Flash::set($type, $message);
    }

    /**
     * Enforce authentication and (optionally) role membership.
     * Redirects unauthenticated users to /login. Renders 403 if role isn't allowed.
     */
    protected function requireAuth(?array $roles = null): void
    {
        if (Session::userId() === null) {
            $this->flash('error', 'Please sign in first.');
            $this->redirect('/login');
        }
        if ($roles !== null && !in_array(Session::role(), $roles, true)) {
            (new ErrorController())->render403();
            exit;
        }
    }

    protected function requireCsrf(): void
    {
        if (!$this->isPost()) {
            return;
        }
        if (!Csrf::check(Csrf::fromRequest())) {
            (new ErrorController())->render400('CSRF token invalid or missing.');
            exit;
        }
    }

    protected function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strcasecmp($xrw, 'XMLHttpRequest') === 0
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');
    }

    protected function postParam(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function queryParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
