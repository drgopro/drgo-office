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

    // 의뢰자용 공개 견적서 링크의 도메인 (office 서브도메인 노출 방지)
    // 예: ESTIMATE_PUBLIC_BASE_URL=https://e.drgo.pro — 미설정 시 APP_URL 사용
    'estimate_share' => [
        'base_url' => env('ESTIMATE_PUBLIC_BASE_URL'),
    ],

    // 채널톡 팀챗 알림 — 채널톡 설정 > API 관리에서 액세스 키/시크릿 발급
    // group: 메시지를 받을 팀챗 그룹 이름(또는 그룹 ID)
    'channeltalk' => [
        'access_key' => env('CHANNELTALK_ACCESS_KEY'),
        'access_secret' => env('CHANNELTALK_ACCESS_SECRET'),
        'group' => env('CHANNELTALK_GROUP'),
        'bot_name' => env('CHANNELTALK_BOT_NAME', '오피스 알림'),
        'remind_days' => env('CHANNELTALK_REMIND_DAYS', 2),
    ],

    // 페이앱 결제 연동 (견적서 결제) — 페이앱 판매자 설정에서 연동 KEY/VALUE 발급
    'payapp' => [
        'userid' => env('PAYAPP_USERID'),
        'linkkey' => env('PAYAPP_LINKKEY'),
        'linkval' => env('PAYAPP_LINKVAL'),
        'api_url' => env('PAYAPP_API_URL', 'https://api.payapp.kr/oapi/apiLoad.html'),
    ],

    // 시세 크롤링 국내 경유 프록시 (해외 IP 차단 판매처용)
    // 예: MARKET_PRICE_PROXY=http://user:pass@1.2.3.4:8888 / PROXY_VENDORS는 쉼표 구분(빈 값이면 전체)
    'market_price' => [
        'proxy' => env('MARKET_PRICE_PROXY'),
        'proxy_vendors' => env('MARKET_PRICE_PROXY_VENDORS', 'pcfactory'),
    ],

    // 셀프호스팅 delivery-tracker (배송 추적)
    'delivery_tracker' => [
        'url' => env('DELIVERY_TRACKER_URL'), // 셀프호스팅 인스턴스 (예: http://127.0.0.1:8150)
        // 공식 호스팅 API (apis.tracker.delivery) — 키가 설정되면 셀프호스팅보다 우선 사용
        'client_id' => env('DELIVERY_TRACKER_CLIENT_ID'),
        'client_secret' => env('DELIVERY_TRACKER_CLIENT_SECRET'),
    ],

];
