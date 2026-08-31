<?php
return [
    'name' => env('APP_NAME', 'Gmail Automation Engine'),
    'env' => env('APP_ENV', 'production'),
    'key' => env('APP_KEY', 'base64:32characterRandomSecretKeyForEncryption=='),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
    'locale' => 'en',
];
