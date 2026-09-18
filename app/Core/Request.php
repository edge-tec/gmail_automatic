<?php
namespace App\Core;

class Request {
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $json;

    public function __construct(
        mixed $get = null, 
        mixed $post = null, 
        mixed $server = null, 
        ?array $files = null,
        mixed $extra = null
    ) {
        if (is_string($get)) {
            $method = $get;
            $uri = is_string($post) ? $post : '/';
            $args = func_get_args();
            $postData = [];
            for ($i = 2; $i < count($args); $i++) {
                if (is_array($args[$i]) && !empty($args[$i])) {
                    $postData = array_merge($postData, $args[$i]);
                }
            }
            $this->get = [];
            $this->post = $postData;
            $this->server = array_merge($_SERVER ?? [], ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
            $this->files = [];
            $this->json = [];
            return;
        }

        if (is_array($extra) && (isset($extra['REQUEST_URI']) || isset($extra['REQUEST_METHOD']))) {
            $server = array_merge(is_array($server) ? $server : [], $extra);
        }

        $this->get = is_array($get) ? $get : $_GET;
        $this->post = is_array($post) ? $post : $_POST;
        $this->server = is_array($server) ? $server : $_SERVER;
        $this->files = is_array($files) ? $files : $_FILES;

        $contentType = $this->server['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            $this->json = json_decode($input, true) ?? [];
        } else {
            $this->json = [];
        }
    }

    public function getMethod(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getPath(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');
        if ($position !== false) {
            $uri = substr($uri, 0, $position);
        }
        return '/' . trim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed {
        return $this->post[$key] ?? $this->get[$key] ?? $this->json[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed {
        return $this->post[$key] ?? $default;
    }

    public function has(string $key): bool {
        return isset($this->post[$key]) || isset($this->get[$key]) || isset($this->json[$key]);
    }

    public function server(string $key, mixed $default = null): mixed {
        return $this->server[$key] ?? $default;
    }

    public function all(): array {
        return array_merge($this->get, $this->post, $this->json);
    }

    public function isPost(): bool {
        return $this->getMethod() === 'POST';
    }

    public function isGet(): bool {
        return $this->getMethod() === 'GET';
    }

    public function isAjax(): bool {
        return (!empty($this->server['HTTP_X_REQUESTED_WITH']) &&
            strtolower($this->server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function ip(): string {
        return $this->server['HTTP_X_FORWARDED_FOR'] ?? $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
