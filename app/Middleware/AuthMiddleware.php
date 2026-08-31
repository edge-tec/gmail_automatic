<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class AuthMiddleware {
    public function handle(Request $request): ?string {
        if (!Auth::check()) {
            flash('error', 'Please login to continue.');
            redirect('/login');
            return '';
        }
        return null;
    }
}
