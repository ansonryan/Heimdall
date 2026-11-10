<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 在后台「设置」里新增一组集成配置：Casdoor OIDC 与 TMDB 自动背景。
 * 这些项标记为 system=1，写入的是全局值（不是每个用户各一份）。
 */
return new class extends Migration
{
    private const GROUP_TITLE = '集成配置（OIDC / TMDB）';

    public function up(): void
    {
        $groupId = DB::table('setting_groups')->where('title', self::GROUP_TITLE)->value('id');

        if (!$groupId) {
            $maxOrder = (int) DB::table('setting_groups')->max('order');
            $groupId = DB::table('setting_groups')->insertGetId([
                'title' => self::GROUP_TITLE,
                'order' => $maxOrder + 1,
            ]);
        }

        $items = [
            ['casdoor_enabled', 'boolean', null, '启用 Casdoor 登录', '0'],
            ['casdoor_base_url', 'text', null, 'Casdoor 地址（如 https://auth.example.com）', ''],
            ['casdoor_client_id', 'text', null, 'Casdoor Client ID', ''],
            ['casdoor_client_secret', 'password', null, 'Casdoor Client Secret', ''],
            ['casdoor_redirect_uri', 'text', null, '回调地址（留空自动使用 站点地址/auth/casdoor/callback）', ''],
            ['casdoor_auto_create', 'boolean', null, '首次登录自动创建本地账号', '1'],
            ['tmdb_enabled', 'boolean', null, '启用 TMDB 自动背景', '0'],
            ['tmdb_api_key', 'password', null, 'TMDB API Key（v3 auth）', ''],
            ['tmdb_language', 'text', null, 'TMDB 语言（如 zh-CN）', 'zh-CN'],
            [
                'tmdb_mode',
                'select',
                json_encode(['backdrop' => '横版剧照（推荐）', 'poster' => '竖版海报'], JSON_UNESCAPED_UNICODE),
                '背景图类型',
                'backdrop',
            ],
        ];

        $order = 0;
        foreach ($items as [$key, $type, $options, $label, $default]) {
            $order++;

            $exists = DB::table('settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('settings')->insert([
                'group_id' => $groupId,
                'key'      => $key,
                'type'     => $type,
                'options'  => $options,
                'label'    => $label,
                'value'    => $default,
                'order'    => $order,
                'system'   => 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'casdoor_enabled', 'casdoor_base_url', 'casdoor_client_id', 'casdoor_client_secret',
            'casdoor_redirect_uri', 'casdoor_auto_create',
            'tmdb_enabled', 'tmdb_api_key', 'tmdb_language', 'tmdb_mode',
        ])->delete();

        DB::table('setting_groups')->where('title', self::GROUP_TITLE)->delete();
    }
};
