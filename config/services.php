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

    // 무통장입금 SMS 포워딩 웹훅 인증 토큰
    'bank_deposit' => [
        'token' => env('BANK_DEPOSIT_TOKEN'),
    ],

    // 셀프호스팅 delivery-tracker (배송 추적)
    'delivery_tracker' => [
        'url' => env('DELIVERY_TRACKER_URL'), // 셀프호스팅 인스턴스 (예: http://127.0.0.1:8150)
        // 공식 호스팅 API (apis.tracker.delivery) — 키가 설정되면 셀프호스팅보다 우선 사용
        'client_id' => env('DELIVERY_TRACKER_CLIENT_ID'),
        'client_secret' => env('DELIVERY_TRACKER_CLIENT_SECRET'),
    ],

];
