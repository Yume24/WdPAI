<?php

namespace FurEver\Controllers;

use FurEver\Core\Env;
use FurEver\Core\View;
use Throwable;

final class ErrorController extends AppController
{
    public function render400(string $message = 'Bad request'): void
    {
        http_response_code(400);
        View::output('400', ['title' => 'Bad request – FurEver', 'message' => $message]);
    }

    public function render403(): void
    {
        http_response_code(403);
        View::output('403', ['title' => 'Forbidden – FurEver']);
    }

    public function render404(): void
    {
        http_response_code(404);
        View::output('404', ['title' => 'Not found – FurEver']);
    }

    public function render500(?Throwable $e = null): void
    {
        http_response_code(500);
        $debug = Env::get('APP_ENV', 'production') === 'development';
        $detail = ($debug && $e !== null)
            ? $e->getMessage() . "\n" . $e->getTraceAsString()
            : null;
        View::output('500', ['title' => 'Server error – FurEver', 'detail' => $detail]);
    }
}
