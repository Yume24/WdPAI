<?php

namespace FurEver\Controllers;

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
        if ($e !== null) {
            error_log(sprintf(
                '[FurEver] Unhandled %s: %s in %s:%d',
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
        }
        http_response_code(500);
        View::output('500', ['title' => 'Server error – FurEver']);
    }
}
