<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    /*
    |--------------------------------------------------------------------------
    | Casdoor / OIDC
    |--------------------------------------------------------------------------
    | 只需填写 base_url + client_id + client_secret，其余端点从
    | /.well-known/openid-configuration 自动发现。
    */
    'casdoor' => [
        'enabled'       => filter_var(env('CASDOOR_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'base_url'      => rtrim((string) env('CASDOOR_BASE_URL', 'https://auth.smartryan.com'), '/'),
        'client_id'     => env('CASDOOR_CLIENT_ID'),
        'client_secret' => env('CASDOOR_CLIENT_SECRET'),
        'redirect'      => env('CASDOOR_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/auth/casdoor/callback'),
        'scope'         => env('CASDOOR_SCOPE', 'openid profile email'),
        'button_label'  => env('CASDOOR_BUTTON_LABEL', '使用 Casdoor 登录'),
        // 首次登录自动建号；关闭后只有已绑定用户能进
        'auto_create'   => filter_var(env('CASDOOR_AUTO_CREATE', true), FILTER_VALIDATE_BOOLEAN),
        // 自动建号时是否直接给管理员权限（默认否，可后续在设置页提升）
        'grant_admin'   => filter_var(env('CASDOOR_GRANT_ADMIN', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | TMDB（自动背景海报）
    |--------------------------------------------------------------------------
    */
    'tmdb' => [
        'enabled'  => filter_var(env('TMDB_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'api_key'  => env('TMDB_API_KEY'),
        'language' => env('TMDB_LANGUAGE', 'zh-CN'),
        'region'   => env('TMDB_REGION', 'CN'),
        // backdrop = 横版剧照，poster = 竖版海报（模糊放大后当背景）
        'mode'     => env('TMDB_MODE', 'backdrop'),
        'ttl'      => (int) env('TMDB_TTL', 1800),
    ],

];
