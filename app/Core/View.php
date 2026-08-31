<?php
namespace App\Core;

class View {
    private static string $layout = 'layouts/main';
    private static array $sections = [];
    private static ?string $currentSection = null;

    public static function setLayout(string $layout): void {
        self::$layout = $layout;
    }

    public static function render(string $viewName, array $data = [], ?string $customLayout = null): string {
        extract($data);
        
        $viewFile = view_path(str_replace('.', '/', $viewName) . '.php');
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View file not found: {$viewFile}");
        }

        // Render view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutToUse = $customLayout !== null ? $customLayout : self::$layout;
        if ($layoutToUse === '' || $layoutToUse === false || $layoutToUse === null) {
            return $content;
        }

        $layoutFile = view_path(str_replace('.', '/', $layoutToUse) . '.php');
        if (file_exists($layoutFile)) {
            ob_start();
            require $layoutFile;
            return ob_get_clean();
        }

        return $content;
    }

    public static function section(string $name): void {
        self::$currentSection = $name;
        ob_start();
    }

    public static function endSection(): void {
        if (self::$currentSection !== null) {
            self::$sections[self::$currentSection] = ob_get_clean();
            self::$currentSection = null;
        }
    }

    public static function yield(string $name, string $default = ''): string {
        return self::$sections[$name] ?? $default;
    }
}
