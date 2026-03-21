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

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'default_api_version' => env('META_API_VERSION', 'v20.0'),
        'mode' => env('META_MODE', env('APP_ENV') === 'production' ? 'live' : 'stub'),
        'graph_base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'dialog_base_url' => env('META_DIALOG_BASE_URL', 'https://www.facebook.com'),
        'raw_payload_retention_days' => (int) env('META_RAW_PAYLOAD_RETENTION_DAYS', 90),
        'oauth_state_ttl_minutes' => (int) env('META_OAUTH_STATE_TTL_MINUTES', 10),
        'scopes' => array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', (string) env('META_SCOPES', 'ads_read,business_management,pages_show_list'))
        ))),
        'schedule' => [
            'enabled' => filter_var(env('META_SCHEDULE_ENABLED', true), FILTER_VALIDATE_BOOL),
            'asset_sync_interval_hours' => (int) env('META_ASSET_SYNC_INTERVAL_HOURS', 6),
            'insights_sync_interval_hours' => (int) env('META_INSIGHTS_SYNC_INTERVAL_HOURS', 24),
            'insights_lookback_days' => (int) env('META_INSIGHTS_LOOKBACK_DAYS', 7),
            'rules_window_days' => (int) env('META_RULES_WINDOW_DAYS', 30),
            'recommendation_interval_hours' => (int) env('META_RECOMMENDATION_INTERVAL_HOURS', 24),
            'lock_seconds' => (int) env('META_AUTOMATION_LOCK_SECONDS', 3300),
        ],
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'mock'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'gpt-4.1-mini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.2),
        'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 45),
        'user_agent' => env('AI_USER_AGENT', 'AdsCast/0.2.0'),
    ],

    'reports' => [
        'schedule' => [
            'enabled' => filter_var(env('REPORT_DELIVERIES_ENABLED', true), FILTER_VALIDATE_BOOL),
            'lock_seconds' => (int) env('REPORT_DELIVERIES_LOCK_SECONDS', 840),
        ],
    ],

];
