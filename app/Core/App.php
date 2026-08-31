<?php
namespace App\Core;

class App {
    private Router $router;
    private Request $request;

    public function __construct() {
        $this->loadEnvironment();
        $this->setTimezone();
        DatabaseSanitizer::runOnce();
        $this->router = new Router();
        $this->request = new Request();
    }

    private function loadEnvironment(): void {
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
            $dotenv->safeLoad();
        }
    }

    private function setTimezone(): void {
        $tz = config('app.timezone', 'Asia/Dhaka');
        date_default_timezone_set($tz);
    }

    public function getRouter(): Router {
        return $this->router;
    }

    public function run(): void {
        Session::start();
        $routesFile = base_path('routes/web.php');
        if (file_exists($routesFile)) {
            $router = $this->router;
            require $routesFile;
        }

        try {
            $response = $this->router->dispatch($this->request);
            if (is_string($response)) {
                echo $response;
            }
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    private function handleException(\Throwable $e): void {
        error_log("Application Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());

        if (config('app.debug', false)) {
            http_response_code(500);
            echo "<h1>Application Error (Debug Mode)</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (line " . $e->getLine() . ")</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            http_response_code(500);
            echo View::render('errors/500', ['message' => 'Internal Server Error']);
        }
    }
}
