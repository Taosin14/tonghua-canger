<?php
namespace App\Core;

class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:string,1:string}}> [method, pattern, [class, action]] */
    private array $routes = [];

    public function add(string $method, string $pattern, string $class, string $action): void
    {
        $this->routes[] = [$method, $pattern, [$class, $action]];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                [$class, $action] = $handler;
                (new $class())->{$action}(...$params);
                return;
            }
        }
        Response::error(404, '接口不存在: ' . $method . ' ' . $path);
    }
}
