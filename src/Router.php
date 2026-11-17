<?php

declare(strict_types=1);

namespace Buzlook;

/**
 * Minimal regex based router. Route patterns use a {code} placeholder for a tag code.
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function dispatch(string $method, string $uri, View $view): void
    {
        $path = rtrim((string) parse_url($uri, PHP_URL_PATH), '/') ?: '/';
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $pathMatched = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            ($route['handler'])(...array_values($parameters));
            return;
        }

        if (str_starts_with($path, '/api/')) {
            http_response_code($pathMatched ? 405 : 404);
            header('Content-Type: application/json');
            echo json_encode(['error' => $pathMatched ? 'Method not allowed.' : 'Unknown API endpoint.']);
            return;
        }

        if ($pathMatched) {
            http_response_code(405);
            $view->render('message', [
                'title' => $view->t('error.method_not_allowed.title'),
                'message' => $view->t('error.method_not_allowed.message'),
            ]);
            return;
        }

        http_response_code(404);
        $view->render('message', [
            'title' => $view->t('error.not_found.title'),
            'message' => $view->t('error.not_found.message'),
        ]);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = str_replace('{code}', '(?P<code>' . CodeGenerator::PATTERN . ')', $pattern);
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#D',
            'handler' => $handler,
        ];
    }
}
