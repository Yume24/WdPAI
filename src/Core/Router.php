<?php

namespace FurEver\Core;

use FurEver\Controllers\ErrorController;
use Throwable;

final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,params:string[],controller:string,action:string}> */
    private array $routes = [];

    public function add(string $method, string $pattern, string $controller, string $action): void
    {
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $pattern,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    public function get(string $pattern, string $controller, string $action): void
    {
        $this->add('GET', $pattern, $controller, $action);
    }

    public function post(string $pattern, string $controller, string $action): void
    {
        $this->add('POST', $pattern, $controller, $action);
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }

        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[$route['method']] = true;
                continue;
            }

            foreach ($route['params'] as $name) {
                if (isset($matches[$name])) {
                    $_GET[$name] = $matches[$name];
                }
            }

            try {
                $controllerClass = $route['controller'];
                $controller = new $controllerClass();
                $controller->{$route['action']}();
            } catch (Throwable $e) {
                (new ErrorController())->render500($e);
            }
            return;
        }

        if (!empty($allowed)) {
            (new ErrorController())->render400('Method ' . $method . ' not allowed for ' . $path);
            return;
        }

        (new ErrorController())->render404();
    }
}
