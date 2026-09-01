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

    public function googleApiDisclosure(Request $request): string {
        return View::render('public/google_api_disclosure', [], 'layouts/public');
    }

    public function zeroFallbackPolicy(Request $request): string {
        return View::render('public/zero_fallback_policy', [], 'layouts/public');
    }

    public function dataSecurity(Request $request): string {
        return View::render('public/data_security', [], 'layouts/public');
    }
}
