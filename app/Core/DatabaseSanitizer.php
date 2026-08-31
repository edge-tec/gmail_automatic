<?php
namespace App\Core;

class DatabaseSanitizer {
    private static bool $hasRun = false;

    public static function runOnce(): void {
        if (self::$hasRun) {
            return;
        }
        self::$hasRun = true;

        try {
            // 1. Purge any legacy default boilerplate from automation_settings table
            Database::execute(
                "UPDATE automation_settings 
                 SET reply_message = NULL 
                 WHERE reply_message LIKE '%Automated Support%' 
                    OR reply_message LIKE '%Thank you for reaching out%'
                    OR reply_message LIKE '%received your message%'
                    OR reply_message = 'Where are you located?'"
            );

            // 2. Cancel any pending queue jobs containing old boilerplate text
            Database::execute(
                "UPDATE scheduled_jobs 
                 SET status = 'cancelled', last_error = 'Purged legacy default boilerplate' 
                 WHERE status = 'pending' 
                   AND (payload LIKE '%Automated Support%' 
                     OR payload LIKE '%Thank you for reaching out%'
                     OR payload LIKE '%received your message%')"
            );
        } catch (\Throwable $e) {
            // Silently ignore if tables not yet migrated
        }
    }
}
