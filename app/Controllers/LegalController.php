<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\View;

class LegalController {
    public function privacy(Request $request): string {
        return View::render('public/privacy', [], 'layouts/public');
    }

    public function terms(Request $request): string {
        return View::render('public/terms', [], 'layouts/public');
    }
}
