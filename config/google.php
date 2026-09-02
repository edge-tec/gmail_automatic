<?php
return [
    'client_id' => env('GOOGLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
    'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://localhost:8000/auth/google/callback'),
    'pubsub_topic' => env('GOOGLE_PUBSUB_TOPIC', ''),
    'pubsub_token' => env('GOOGLE_PUBSUB_VERIFICATION_TOKEN', ''),
    'scopes' => [
        'https://mail.google.com/',
        'https://www.googleapis.com/auth/gmail.modify',
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.compose',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
        'openid',
    ],
    'access_type' => 'offline',
    'prompt' => 'consent select_account',
];
