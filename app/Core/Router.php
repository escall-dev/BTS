<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $uri, string $controller): void
    {
        $this->routes[$method][] = [
            'uri' => $uri,
            'controller' => $controller,
        ];
    }

    public function dispatch(string $uri, string $method): void
    {
        $route = $this->match($uri, $method);

        if ($route === null) {
            http_response_code(404);
            echo '404 - Page not found';
            return;
        }

        [$controllerName, $action] = explode('@', $route['controller']);
        $controllerClass = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $action)) {
            http_response_code(500);
            echo 'Controller action not found';
            return;
        }

        (new $controllerClass())->$action(...$route['params']);
    }

    private function match(string $uri, string $method): ?array
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $route['uri']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                return [
                    'controller' => $route['controller'],
                    'params' => array_map('urldecode', $matches),
                ];
            }
        }

        return null;
    }
}