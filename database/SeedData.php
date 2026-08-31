<?php
namespace Database;

use App\Core\Database;
use App\Models\User;
use App\Models\SystemSetting;

class SeedData {
    public static function run(): void {
        // Create default Admin User if not exists
        $admin = User::findByEmail('admin@example.com');
        if (!$admin) {
            User::create([
                'name' => 'System Admin',
                'email' => 'admin@example.com',
                'password' => password_hash('Admin@123456', PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
            ]);
        }

        // Initialize default system settings
        $defaults = [
            'app_name' => 'Gmail Auto Reply & Follow-up Automation',
            'google_client_id' => '',
            'google_client_secret' => '',
            'google_redirect_uri' => 'http://localhost:8000/auth/google/callback',
            'google_pubsub_topic' => '',
            'google_pubsub_token' => '',
            'global_automation_enabled' => '1',
            'cron_last_run' => '',
        ];

        foreach ($defaults as $key => $val) {
            $existing = SystemSetting::get($key);
            if ($existing === null) {
                SystemSetting::set($key, $val);
            }
        }
    }
}
