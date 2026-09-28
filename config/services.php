<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Kunci server Midtrans (sandbox untuk belajar). Dibaca lewat config(), bukan env(), supaya tetap
    // terbaca setelah php artisan config:cache.
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    ],

    // Kunci API TMDB, dipakai MovieSeeder dan FilmAkanTayangSeeder untuk mengambil data film.
    'tmdb' => [
        'key' => env('TMDB_API_KEY'),
    ],

    'ml' => [
        'url' => env('ML_API_URL', 'http://127.0.0.1:8001'),

        // Batas waktu (detik) menunggu rekomendasi. Dipakai di beranda, jadi dibuat singkat.
        'timeout' => env('ML_TIMEOUT', 5),

        // Batas waktu (detik) analisis sentimen. Boleh lebih lama karena hanya
        // dipanggil saat penonton mengirim form masukan.
        'sentiment_timeout' => env('ML_SENTIMENT_TIMEOUT', 30),
    ],

];
