<?php
namespace App\Core;

class Router {
    private array $routes = [];
    private array $middlewareGroups = [];

    public function get(string $path, string|array|callable $action, array $middleware = []): void {
        $this->addRoute('GET', $path, $action, $middleware);
    }

    public function post(string $path, string|array|callable $action, array $middleware = []): void {
        $this->addRoute('POST', $path, $action, $middleware);
    }

    public function any(string $path, string|array|callable $action, array $middleware = []): void {
        $this->addRoute('GET', $path, $action, $middleware);
        $this->addRoute('POST', $path, $action, $middleware);
    }

    private function addRoute(string $method, string $path, string|array|callable $action, array $middleware = []): void {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', trim($path, '/'));
        $regex = '#^/' . ($pattern ? $pattern : '') . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => '/' . trim($path, '/'),
            'regex' => $regex,
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): mixed {
        $method = $request->getMethod();
        $path = $request->getPath();

        // 0. Check active SEO Redirects (301 / 302)
        try {
            $redirect = \App\Models\SeoRedirect::findByOldUrl($path);
            if ($redirect) {
                $redirect->incrementHit();
                $targetUrl = str_starts_with($redirect->new_url, 'http') ? $redirect->new_url : url($redirect->new_url);
                if (!headers_sent()) {
                    header("Location: {$targetUrl}", true, $redirect->status_code);
                    exit;
                }
            }
        } catch (\Throwable $e) {
            // Ignore if DB not ready
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['regex'], $path, $matches)) {
                // Execute Middlewares
                foreach ($route['middleware'] as $mw) {
                    $mwInstance = is_string($mw) ? new $mw() : $mw;
                    $response = $mwInstance->handle($request);
                    if ($response !== null) {
                        return $response;
                    }
                }

                // Extract named params
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);

                $action = $route['action'];
                if (is_callable($action)) {
                    return call_user_func_array($action, array_values($params));
                }

                if (is_array($action)) {
                    [$controllerClass, $methodName] = $action;
                    $controller = new $controllerClass();
                    return call_user_func_array([$controller, $methodName], array_merge([$request], array_values($params)));
                }

                if (is_string($action) && str_contains($action, '@')) {
                    [$controllerClass, $methodName] = explode('@', $action);
                    $fullClass = "App\\Controllers\\{$controllerClass}";
                    $controller = new $fullClass();
                    return call_user_func_array([$controller, $methodName], array_merge([$request], array_values($params)));
                }
            }
        }

        // 404 Not Found
        http_response_code(404);
        return View::render('errors/404', ['message' => 'Page Not Found']);
    }
}
