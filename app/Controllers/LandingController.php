<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Plan;
use App\Models\SystemSetting;

class LandingController {
    public function index(): string {
        $plans = Plan::getActivePlans();
        
        $trialEnabled = (bool)(int)SystemSetting::get('trial_enabled', '1');
        $trialDays = (int)SystemSetting::get('trial_duration_days', '14');
        $trialGmailLimit = (int)SystemSetting::get('trial_gmail_limit', '5');

        return View::render('public/landing', [
            'plans' => $plans,
            'trialEnabled' => $trialEnabled,
            'trialDays' => $trialDays,
            'trialGmailLimit' => $trialGmailLimit,
        ], 'public');
    }
}
