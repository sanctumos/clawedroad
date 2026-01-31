<?php

declare(strict_types=1);

/**
 * Minimal router: match path and method, call handler.
 */
final class Router
{
    private string $basePath;
    private string $method;
    private array $routes = [];

    public function __construct(string $basePath = '', string $method = 'GET')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->method = strtoupper($method);
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function run(): void
    {
        $path = $this->getPath();
        foreach ($this->routes as [$method, $pattern, $handler]) {
            if ($method !== $this->method) {
                continue;
            }
            if ($this->match($pattern, $path)) {
                $handler();
                return;
            }
        }
        $this->notFound();
    }

    private function getPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $q = strpos($uri, '?');
        $path = $q !== false ? substr($uri, 0, $q) : $uri;
        $path = rawurldecode($path);
        if ($this->basePath !== '' && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }

    private function match(string $pattern, string $path): bool
    {
        $p = trim($pattern, '/');
        $pathTrimmed = trim($path, '/');
        if ($p === '' && $pathTrimmed === '') {
            return true;
        }
        $regex = '/^' . str_replace('/', '\/', $p) . '$/';
        return (bool) preg_match($regex, $pathTrimmed);
    }

    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
    }
}
