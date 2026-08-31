<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;

class AdminMiddleware {
    public function handle(Request $request): ?string {
        if (!Auth::check()) {
            flash('error', 'Please login to access the admin panel.');
            redirect('/login');
            return '';
        }

        if (!Auth::isAdmin()) {
            flash('error', 'Unauthorized. Admin access required.');
            redirect('/dashboard');
            return '';
        }

        return null;
    }
}
