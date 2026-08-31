<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Application
{
    public function __construct(private readonly array $routes)
    {
    }

    public function run(): void
    {
        try {
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $path = '/' . trim($path, '/');
            $path = $path === '/' ? '/' : rtrim($path, '/');

            foreach ($this->routes as [$routeMethod, $routePath, $handler, $middleware]) {
                if ($method !== $routeMethod) {
                    continue;
                }

                $parameters = $this->matchRoute($routePath, $path);
                if ($parameters === null) {
                    continue;
                }

                $this->runMiddleware($middleware);
                [$controller, $action] = $handler;
                (new $controller())->{$action}(...$parameters);
                return;
            }

            http_response_code(404);
            echo 'Page not found.';
        } catch (Throwable $exception) {
            http_response_code(500);
            if (Env::get('APP_DEBUG', false)) {
                echo '<pre>' . htmlspecialchars((string) $exception) . '</pre>';
            } else {
                echo 'Something went wrong. Please try again.';
            }
        }
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $name) {
            if ($name === 'auth' && !Auth::check()) {
                $this->redirect('/login');
            }
            if ($name === 'guest' && Auth::check()) {
                $this->redirect('/');
            }
            if ($name === 'csrf' && !Csrf::verify($_POST['_token'] ?? null)) {
                http_response_code(419);
                exit('Your session expired. Please go back and try again.');
            }
            if (str_starts_with($name, 'role:')) {
                $allowed = explode(',', substr($name, 5));
                $role = Auth::user()['role'] ?? '';
                if (!in_array($role, $allowed, true)) {
                    http_response_code(403);
                    exit('You do not have permission to access this page.');
                }
            }
        }
    }

    private function matchRoute(string $route, string $path): ?array
    {
        $routeSegments = $route === '/' ? [] : explode('/', trim($route, '/'));
        $pathSegments = $path === '/' ? [] : explode('/', trim($path, '/'));

        if (count($routeSegments) !== count($pathSegments)) {
            return null;
        }

        $parameters = [];
        foreach ($routeSegments as $index => $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $parameters[] = urldecode($pathSegments[$index]);
                continue;
            }
            if ($segment !== $pathSegments[$index]) {
                return null;
            }
        }

        return $parameters;
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
