<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Services\SettingService;

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => fn () => app(SettingService::class)->get(SettingKey::GOOGLE_CLIENT_ID, env('GOOGLE_CLIENT_ID')),
        'client_secret' => fn () => app(SettingService::class)->get(SettingKey::GOOGLE_CLIENT_SECRET, env('GOOGLE_CLIENT_SECRET')),
        'redirect' => fn () => app(SettingService::class)->get(SettingKey::GOOGLE_REDIRECT_URL, env('GOOGLE_REDIRECT_URL')),
    ],

];
