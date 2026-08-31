<?php
/**
 * Application Entry Point
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \App\Core\App();
$app->run();
