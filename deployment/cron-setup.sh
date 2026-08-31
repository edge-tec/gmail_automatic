#!/bin/bash
# aaPanel Cron Setup Helper
# Run every minute: * * * * *
# Command:
# cd /www/wwwroot/your-domain.com && php cron.php >> storage/logs/cron.log 2>&1

PROJECT_DIR=$(pwd)

echo "Setting up aaPanel background automation for directory: ${PROJECT_DIR}"
echo ""
echo "=== 1. Add Cron Job in aaPanel (Cron menu) ==="
echo "Type of Task: Shell Script"
echo "Name of Task: Gmail Automation Poller"
echo "Period: Every Minute (* * * * *)"
echo "Script Content:"
echo "--------------------------------------------------------"
echo "cd ${PROJECT_DIR} && php cron.php >> storage/logs/cron.log 2>&1"
echo "--------------------------------------------------------"
echo ""
echo "=== 2. Optional: Setup Process Supervisor in aaPanel ==="
echo "Install 'Process Supervisor' from aaPanel App Store"
echo "Add Program:"
echo "  Name: gmail-worker"
echo "  Command: php ${PROJECT_DIR}/worker.php"
echo "  Directory: ${PROJECT_DIR}"
echo "  User: www"
echo "  Processes: 2"
echo "--------------------------------------------------------"
