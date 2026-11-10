<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Casdoor / 通用 OIDC 登录。
 *
 * 只依赖标准端点，不引入 Socialite：
 *   GET  /auth/casdoor            -> 302 到 authorization_endpoint
 *   GET  /auth/casdoor/callback   -> 换 token、取 userinfo、绑定或建号、登录
 */
class CasdoorController extends Controller
{
    private const PROVIDER = 'casdoor';

    public function redirect(Request $request): RedirectResponse
    {
        $cfg = $this->cfg();
        abort_unless($cfg['enabled'] && $cfg['client_id'] && $cfg['client_secret'], 404);

        try {
            $endpoints = $this->endpoints($cfg);
        } catch (\Throwable $e) {
            Log::error('Casdoor discovery failed: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['casdoor' => '无法连接 Casdoor，请检查地址与网络。']);
        }

        $state = Str::random(48);
        $request->session()->put('casdoor_state', $state);
        $request->session()->put('casdoor_back', url()->previous());

        $query = http_build_query([
            'client_id'     => $cfg['client_id'],
            'redirect_uri'  => $cfg['redirect'],
            'response_type' => 'code',
            'scope'         => $cfg['scope'],
            'state'         => $state,
        ]);

        return redirect()->away($endpoints['authorization_endpoint'] . '?' . $query);
    }

    public function callback(Request $request): RedirectResponse
    {
        $cfg = $this->cfg();
        abort_unless($cfg['enabled'], 404);

        if ($request->filled('error')) {
            return redirect()->route('login')
                ->withErrors(['casdoor' => 'Casdoor 返回错误：' . $request->string('error_description', $request->string('error'))]);
        }

        $state = (string) $request->session()->pull('casdoor_state', '');
        if ($state === '' || !hash_equals($state, (string) $request->query('state', ''))) {
            return redirect()->route('login')->withErrors(['casdoor' => '登录状态校验失败，请重试。']);
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return redirect()->route('login')->withErrors(['casdoor' => '缺少授权码。']);
        }

        try {
            $endpoints = $this->endpoints($cfg);

            $tokenResponse = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($endpoints['token_endpoint'], [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'client_id'     => $cfg['client_id'],
                    'client_secret' => $cfg['client_secret'],
                    'redirect_uri'  => $cfg['redirect'],
                ]);

            if (!$tokenResponse->successful()) {
                Log::warning('Casdoor token exchange failed', ['status' => $tokenResponse->status(), 'body' => $tokenResponse->body()]);
                return redirect()->route('login')->withErrors(['casdoor' => '换取令牌失败，请检查 Client ID / Secret。']);
            }

            $accessToken = (string) $tokenResponse->json('access_token', '');
            if ($accessToken === '') {
                return redirect()->route('login')->withErrors(['casdoor' => 'Casdoor 未返回 access_token。']);
            }

            // 用 userinfo 取身份：等价于校验 token 有效（provider 侧已完成验签）
            $infoResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->get($endpoints['userinfo_endpoint']);

            if (!$infoResponse->successful()) {
                Log::warning('Casdoor userinfo failed', ['status' => $infoResponse->status()]);
                return redirect()->route('login')->withErrors(['casdoor' => '获取用户信息失败。']);
            }

            $info = $infoResponse->json();
        } catch (\Throwable $e) {
            Log::error('Casdoor callback error: ' . $e->getMessage());
            return redirect()->route('login')->withErrors(['casdoor' => '连接 Casdoor 失败：' . $e->getMessage()]);
        }

        $sub   = (string) ($info['sub'] ?? $info['id'] ?? '');
        $email = (string) ($info['email'] ?? '');
        $name  = (string) ($info['preferred_username'] ?? $info['name'] ?? ($email !== '' ? strstr($email, '@', true) : ''));

        if ($sub === '') {
            return redirect()->route('login')->withErrors(['casdoor' => 'Casdoor 未返回 sub，无法绑定账号。']);
        }

        $user = User::where('oidc_provider', self::PROVIDER)->where('oidc_sub', $sub)->first();

        if (!$user && $email !== '') {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->forceFill(['oidc_sub' => $sub, 'oidc_provider' => self::PROVIDER])->save();
            }
        }

        if (!$user) {
            if (!$cfg['auto_create']) {
                return redirect()->route('login')->withErrors(['casdoor' => '该 Casdoor 账号尚未绑定本地用户。']);
            }
            $user = User::create([
                'username' => $this->uniqueUsername($name !== '' ? $name : 'casdoor'),
                'email'    => $email,
                'password' => bcrypt(Str::random(40)),
            ]);
            $user->forceFill(['oidc_sub' => $sub, 'oidc_provider' => self::PROVIDER])->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $back = (string) $request->session()->pull('casdoor_back', '');
        return redirect()->to($this->safeBack($back));
    }

    /**
     * 配置来源优先级：后台设置（settings 表） > .env。
     */
    private function cfg(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $get = static function (string $key, $default = null) {
            try {
                $value = \App\Setting::fetch($key);
            } catch (\Throwable $e) {
                $value = null;
            }
            return ($value === null || $value === '') ? $default : $value;
        };

        $base = rtrim((string) $get('casdoor_base_url', config('services.casdoor.base_url')), '/');

        $redirect = (string) $get('casdoor_redirect_uri', '');
        if ($redirect === '') {
            $redirect = rtrim((string) (config('app.url') ?: url('/')), '/') . '/auth/casdoor/callback';
        }

        $cache = [
            'enabled'       => filter_var($get('casdoor_enabled', config('services.casdoor.enabled')), FILTER_VALIDATE_BOOLEAN),
            'base_url'      => $base,
            'client_id'     => (string) $get('casdoor_client_id', config('services.casdoor.client_id')),
            'client_secret' => (string) $get('casdoor_client_secret', config('services.casdoor.client_secret')),
            'redirect'      => $redirect,
            'scope'         => (string) config('services.casdoor.scope', 'openid profile email'),
            'auto_create'   => filter_var($get('casdoor_auto_create', config('services.casdoor.auto_create')), FILTER_VALIDATE_BOOLEAN),
            'button_label'  => (string) config('services.casdoor.button_label', '使用 Casdoor 登录'),
        ];

        return $cache;
    }

    /**
     * 读取并缓存 OIDC 发现文档。
     */
    private function endpoints(array $cfg): array
    {
        $cacheKey = 'casdoor.discovery.' . md5($cfg['base_url']);

        return Cache::remember($cacheKey, 86400, function () use ($cfg) {
            $response = Http::acceptJson()->timeout(10)
                ->get($cfg['base_url'] . '/.well-known/openid-configuration');

            if (!$response->successful()) {
                throw new \RuntimeException('discovery HTTP ' . $response->status());
            }

            $json = $response->json();
            foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint'] as $key) {
                if (empty($json[$key])) {
                    throw new \RuntimeException('discovery missing ' . $key);
                }
            }

            return $json;
        });
    }

    private function uniqueUsername(string $base): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '', $base) ?: 'casdoor';
        $candidate = $slug;
        $i = 1;
        while (User::where('username', $candidate)->exists()) {
            $candidate = $slug . $i++;
        }
        return $candidate;
    }

    private function safeBack(string $url): string
    {
        // 只允许回到本站，避免开放重定向
        $host = parse_url($url, PHP_URL_HOST);
        $self = parse_url(config('app.url') ?: url('/'), PHP_URL_HOST);
        return ($host && $host === $self) ? $url : '/';
    }
}
