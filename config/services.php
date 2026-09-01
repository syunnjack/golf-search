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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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


    'ga4' => [
        'id' => env('GA4_MEASUREMENT_ID'),
    ],

    'rakuten' => [
        'app_id' => env('RAKUTEN_APP_ID'),
        'access_key' => env('RAKUTEN_ACCESS_KEY'),
        'affiliate_id' => env('RAKUTEN_AFFILIATE_ID'),

        // **楽天市場APIは別のアプリが要る。** 新しい楽天ウェブサービスは
        // アプリを作るときに使うAPIが決まり、あとから足せない。
        // GORA・トラベル用のアプリでは "API Configuration not found" が返る。
        // 未設定のときは上の資格情報にそのまま落とす（設定するまで用品は出ない）。
        'ichiba_app_id' => env('RAKUTEN_ICHIBA_APP_ID', env('RAKUTEN_APP_ID')),
        'ichiba_access_key' => env('RAKUTEN_ICHIBA_ACCESS_KEY', env('RAKUTEN_ACCESS_KEY')),
    ],

];
