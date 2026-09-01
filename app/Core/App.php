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

        http_response_code(500);
        if (config('app.debug', false)) {
            echo "<h1>Application Error (Debug Mode)</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (line " . $e->getLine() . ")</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            try {
                $layout = Auth::check() ? 'layouts/main' : 'layouts/public';
                echo View::render('errors/500', ['message' => 'Something went wrong while processing your request. Please try again or contact support.'], $layout);
            } catch (\Throwable $renderEx) {
                echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>500 - Internal Server Error</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light d-flex align-items-center justify-content-center min-vh-100"><div class="card p-5 text-center shadow-sm" style="max-width: 500px;"><div class="display-1 fw-bold text-danger mb-2">500</div><h4 class="fw-bold mb-2">Internal Server Error</h4><p class="text-muted small mb-4">Something went wrong while processing your request. Please check back shortly.</p><a href="/" class="btn btn-primary px-4">Return to Home</a></div></body></html>';
            }
        }
    }
}
