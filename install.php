<?php
/**
 * Automated Installer for Gmail Auto Reply & Follow-up Automation
 * Run via CLI: php install.php
 * Or access via Browser: http://your-domain.com/install.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use Database\MigrationRunner;

// If already installed and accessed via web, redirect to login
$isCli = (php_sapi_name() === 'cli');

if (!$isCli && file_exists(__DIR__ . '/.env') && file_exists(__DIR__ . '/storage/installed.lock')) {
    echo "<h3>System is already installed!</h3><p><a href='/login'>Go to Login</a></p>";
    exit;
}

$errors = [];
$success = false;

// Ensure storage directories exist with proper permissions
$storageDirs = [
    __DIR__ . '/storage',
    __DIR__ . '/storage/logs',
    __DIR__ . '/storage/database',
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

// Generate APP_KEY if needed
$appKey = 'base64:' . base64_encode(random_bytes(32));

if ($isCli) {
    echo "=====================================================\n";
    echo "  Gmail Auto Reply & Follow-up Automation Installer  \n";
    echo "=====================================================\n\n";

    if (!file_exists(__DIR__ . '/.env')) {
        copy(__DIR__ . '/.env.example', __DIR__ . '/.env');
        // Replace key
        $envContent = file_get_contents(__DIR__ . '/.env');
        $envContent = preg_replace('/APP_KEY=.*/', "APP_KEY={$appKey}", $envContent);
        file_put_contents(__DIR__ . '/.env', $envContent);
        echo "✓ Created .env file with generated secure APP_KEY.\n";
    }

    // Run migrations
    try {
        MigrationRunner::run();
        touch(__DIR__ . '/storage/installed.lock');
        echo "\n✓ Installation completed successfully!\n";
        echo "Default Admin Login:\n";
        echo "  Email:    admin@example.com\n";
        echo "  Password: Admin@123456\n";
        echo "\nNext steps:\n";
        echo "  1. Configure your Google Cloud OAuth credentials in .env or Admin Settings.\n";
        echo "  2. Setup aaPanel Cron: * * * * * cd " . __DIR__ . " && php cron.php >> storage/logs/cron.log 2>&1\n";
        echo "  3. Setup aaPanel Supervisor for background worker: php " . __DIR__ . "/worker.php\n";
    } catch (\Throwable $e) {
        echo "\n✗ Installation error: " . $e->getMessage() . "\n";
        exit(1);
    }
    exit(0);
}

// Web-based form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbType = $_POST['db_type'] ?? 'mysql';
    $dbHost = $_POST['db_host'] ?? '127.0.0.1';
    $dbPort = $_POST['db_port'] ?? '3306';
    $dbName = $_POST['db_name'] ?? 'gmail_automation';
    $dbUser = $_POST['db_user'] ?? 'root';
    $dbPass = $_POST['db_pass'] ?? '';
    $appUrl = rtrim($_POST['app_url'] ?? 'http://localhost:8000', '/');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');
    $adminPass = $_POST['admin_password'] ?? 'Admin@123456';

    $envData = [
        'APP_NAME' => '"Gmail Automation Engine"',
        'APP_ENV' => 'production',
        'APP_KEY' => $appKey,
        'APP_DEBUG' => 'false',
        'APP_URL' => $appUrl,
        'APP_TIMEZONE' => 'Asia/Dhaka',
        'DB_CONNECTION' => $dbType,
        'DB_HOST' => $dbHost,
        'DB_PORT' => $dbPort,
        'DB_DATABASE' => $dbType === 'sqlite' ? 'storage/database/database.sqlite' : $dbName,
        'DB_USERNAME' => $dbUser,
        'DB_PASSWORD' => $dbPass,
        'GOOGLE_CLIENT_ID' => '',
        'GOOGLE_CLIENT_SECRET' => '',
        'GOOGLE_REDIRECT_URI' => $appUrl . '/auth/google/callback',
    ];

    $envStr = "";
    foreach ($envData as $k => $v) {
        $envStr .= "{$k}={$v}\n";
    }

    file_put_contents(__DIR__ . '/.env', $envStr);

    try {
        MigrationRunner::run();
        // Update admin if custom email/pass provided
        $admin = \App\Models\User::findByEmail('admin@example.com');
        if ($admin) {
            $admin->update([
                'email' => $adminEmail,
                'password' => password_hash($adminPass, PASSWORD_BCRYPT),
            ]);
        }
        touch(__DIR__ . '/storage/installed.lock');
        $success = true;
    } catch (\Throwable $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gmail Automation Web Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-light p-4">
    <div class="container" style="max-width: 650px;">
        <div class="text-center mb-4">
            <div class="d-inline-flex p-3 bg-primary text-white rounded-3 fs-3 mb-2 shadow-sm">
                <i class="fa-solid fa-bolt-lightning text-warning"></i>
            </div>
            <h3 class="fw-bold">Gmail Automation Installer</h3>
            <p class="text-muted">Production Installation Wizard for aaPanel &amp; Linux VPS</p>
        </div>

        <?php if ($success): ?>
        <div class="card p-4 text-center border-0 shadow">
            <div class="text-success fs-1 mb-2"><i class="fa-solid fa-circle-check"></i></div>
            <h4 class="fw-bold">Installation Successful!</h4>
            <p class="text-muted">The database schema has been migrated and the admin account has been configured.</p>
            <div class="alert alert-light border text-start font-monospace small">
                <strong>Admin Login:</strong><br>
                Email: <?= htmlspecialchars($adminEmail ?? 'admin@example.com') ?><br>
                Password: <?= htmlspecialchars($adminPass ?? 'Admin@123456') ?>
            </div>
            <div>
                <a href="/login" class="btn btn-primary px-4 py-2">Go to Login <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>
        <?php else: ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Installation Failed:</strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow p-4">
            <form method="POST">
                <h5 class="fw-bold mb-3 border-bottom pb-2">1. Application URL</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Website Domain / URL</label>
                    <input type="url" name="app_url" class="form-control" value="http://<?= $_SERVER['HTTP_HOST'] ?? 'localhost:8000' ?>" required>
                </div>

                <h5 class="fw-bold mb-3 mt-4 border-bottom pb-2">2. Database Configuration</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Database Driver</label>
                    <select name="db_type" class="form-select" id="db_type" onchange="toggleDbFields(this.value)">
                        <option value="mysql" selected>MySQL / MariaDB (Recommended for aaPanel)</option>
                        <option value="sqlite">SQLite (Zero-config local file)</option>
                    </select>
                </div>

                <div id="mysql_fields">
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-semibold">Database Host</label>
                            <input type="text" name="db_host" class="form-control" value="127.0.0.1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-semibold">Port</label>
                            <input type="text" name="db_port" class="form-control" value="3306">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Database Name</label>
                        <input type="text" name="db_name" class="form-control" value="gmail_automation">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Database User</label>
                            <input type="text" name="db_user" class="form-control" value="root">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Database Password</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                    </div>
                </div>

                <h5 class="fw-bold mb-3 mt-4 border-bottom pb-2">3. Admin User Setup</h5>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Admin Email</label>
                    <input type="email" name="admin_email" class="form-control" value="admin@example.com" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">Admin Password</label>
                    <input type="password" name="admin_password" class="form-control" value="Admin@123456" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                    <i class="fa-solid fa-play me-1"></i> Start Installation &amp; Migrate
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDbFields(type) {
            document.getElementById('mysql_fields').style.display = (type === 'sqlite') ? 'none' : 'block';
        }
    </script>
</body>
</html>
