<?php
/**
 * Global Helper Functions
 */

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true' || $lower === '(true)') return true;
            if ($lower === 'false' || $lower === '(false)') return false;
            if ($lower === 'null' || $lower === '(null)') return null;
            if ($lower === 'empty' || $lower === '(empty)') return '';
        }
        return $value;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        static $configs = [];
        if ($key === '_reset_') {
            $configs = [];
            return null;
        }

        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset($configs[$file])) {
            $path = dirname(__DIR__, 2) . "/config/{$file}.php";
            if (file_exists($path)) {
                $configs[$file] = require $path;
            } else {
                $configs[$file] = [];
            }
        }

        $current = $configs[$file];
        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return $default;
            }
        }
        return $current;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string {
        return base_path('app' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string {
        return base_path('public' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('view_path')) {
    function view_path(string $path = ''): string {
        return base_path('views' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string {
        $baseUrl = rtrim(config('app.url', 'http://localhost:8000'), '/');
        return $baseUrl . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): void {
        $target = str_starts_with($path, 'http') ? $path : url($path);
        header("Location: {$target}", true, $status);
        exit;
    }
}

if (!function_exists('view')) {
    function view(string $viewName, array $data = []): string {
        return \App\Core\View::render($viewName, $data);
    }
}

if (!function_exists('e')) {
    function e(?string $value): string {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return \App\Core\CSRF::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return \App\Core\CSRF::field();
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?\App\Models\User {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool {
        return \App\Core\Auth::check();
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed {
        if ($key === null) {
            return \App\Core\Session::class;
        }
        return \App\Core\Session::get($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $message = null): mixed {
        if ($message !== null) {
            \App\Core\Session::flash($key, $message);
            return null;
        }
        return \App\Core\Session::getFlash($key);
    }
}

if (!function_exists('flash_messages')) {
    function flash_messages(): string {
        $html = '';
        if ($msg = flash('success')) {
            $html .= '<div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">'
                  . '<i class="fa-solid fa-circle-check fs-5 text-success"></i>'
                  . '<div>' . e($msg) . '</div>'
                  . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                  . '</div>';
        }
        if ($msg = flash('error')) {
            $html .= '<div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">'
                  . '<i class="fa-solid fa-triangle-exclamation fs-5 text-danger"></i>'
                  . '<div>' . e($msg) . '</div>'
                  . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                  . '</div>';
        }
        if ($msg = flash('warning')) {
            $html .= '<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">'
                  . '<i class="fa-solid fa-circle-exclamation fs-5 text-warning"></i>'
                  . '<div>' . e($msg) . '</div>'
                  . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                  . '</div>';
        }
        if ($msg = flash('info')) {
            $html .= '<div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">'
                  . '<i class="fa-solid fa-circle-info fs-5 text-info"></i>'
                  . '<div>' . e($msg) . '</div>'
                  . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
                  . '</div>';
        }
        return $html;
    }
}

if (!function_exists('logger')) {
    function logger(string $message, string $type = 'info', ?int $userId = null, ?int $gmailAccountId = null, array $context = []): void {
        \App\Models\ActivityLog::createLog($type, $message, $userId, $gmailAccountId, $context);
    }
}
