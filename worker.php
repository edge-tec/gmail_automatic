<?php
/**
 * Gmail Automation CLI Worker
 * Run via supervisor or CLI: php worker.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\App;
use App\Services\QueueWorker;

// Initialize app configuration
new App();

$once = in_array('--once', $argv);
$batchSize = 25;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--batch=')) {
        $batchSize = (int)substr($arg, 8);
    }
}

$worker = new QueueWorker();
$worker->run($once, $batchSize);
