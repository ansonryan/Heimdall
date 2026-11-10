<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 自动背景：从 TMDB 取当下热门条目，返回一张可直接当背景的图。
 * 前端定时调用，服务端缓存避免打爆 TMDB 配额。
 */
class BackgroundController extends Controller
{
    private const IMAGE_BASE = 'https://image.tmdb.org/t/p/';

    /**
     * 配置来源优先级：后台设置（settings 表） > .env。
     */
    private function cfg(): array
    {
        $get = static function (string $key, $default = null) {
            try {
                $value = \App\Setting::fetch($key);
            } catch (\Throwable $e) {
                $value = null;
            }
            return ($value === null || $value === '') ? $default : $value;
        };

        return [
            'enabled'  => filter_var($get('tmdb_enabled', config('services.tmdb.enabled')), FILTER_VALIDATE_BOOLEAN),
            'api_key'  => (string) $get('tmdb_api_key', config('services.tmdb.api_key')),
            'language' => (string) $get('tmdb_language', config('services.tmdb.language', 'zh-CN')),
            'mode'     => (string) $get('tmdb_mode', config('services.tmdb.mode', 'backdrop')),
            'ttl'      => (int) config('services.tmdb.ttl', 1800),
        ];
    }

    public function tmdb(): JsonResponse
    {
        $cfg = $this->cfg();

        if (!$cfg['enabled'] || empty($cfg['api_key'])) {
            return response()->json(['url' => null, 'reason' => 'disabled']);
        }

        $cacheKey = 'tmdb.background.' . md5($cfg['api_key'] . $cfg['language'] . $cfg['mode']);

        $payload = Cache::remember($cacheKey, max(60, $cfg['ttl']), function () use ($cfg) {
            try {
                $response = Http::acceptJson()->timeout(12)->get(
                    'https://api.themoviedb.org/3/trending/all/week',
                    [
                        'api_key'  => $cfg['api_key'],
                        'language' => $cfg['language'],
                    ]
                );

                if (!$response->successful()) {
                    Log::warning('TMDB request failed', ['status' => $response->status()]);
                    return null;
                }

                $results = $response->json('results', []);
                if (!is_array($results) || $results === []) {
                    return null;
                }

                $items = [];
                foreach ($results as $item) {
                    $title = $item['title'] ?? $item['name'] ?? '';
                    if ($cfg['mode'] === 'poster' && !empty($item['poster_path'])) {
                        $items[] = [
                            'url'   => self::IMAGE_BASE . 'w780' . $item['poster_path'],
                            'title' => $title,
                            'kind'  => 'poster',
                        ];
                    } elseif (!empty($item['backdrop_path'])) {
                        $items[] = [
                            'url'   => self::IMAGE_BASE . 'w1280' . $item['backdrop_path'],
                            'title' => $title,
                            'kind'  => 'backdrop',
                        ];
                    }
                }

                return $items === [] ? null : $items;
            } catch (\Throwable $e) {
                Log::warning('TMDB exception: ' . $e->getMessage());
                return null;
            }
        });

        if (!is_array($payload)) {
            return response()->json(['url' => null, 'reason' => 'unavailable']);
        }

        $pick = $payload[array_rand($payload)];

        return response()
            ->json($pick)
            ->header('Cache-Control', 'no-store');
    }
}
