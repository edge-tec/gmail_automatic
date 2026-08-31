<?php
namespace App\Middleware;

use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;

class CSRFMiddleware {
    public function handle(Request $request): ?string {
        if ($request->isPost()) {
            // Check if route is exempt (e.g. PubSub webhook)
            $path = $request->getPath();
            if (str_starts_with($path, '/webhook/')) {
                return null;
            }

            if (!CSRF::validate()) {
                if ($request->isAjax()) {
                    Response::json(['error' => 'CSRF token mismatch'], 419);
                }
                flash('error', 'Page session expired. Please submit again.');
                redirect($request->getPath());
                return '';
            }
        }
        return null;
    }
}
