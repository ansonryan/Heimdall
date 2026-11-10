<?php

namespace App\Http\Controllers;

use App\Setting;
use App\SettingGroup;
use Exception;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->middleware('allowed');
    }

    public function index(): View
    {
        $settings = SettingGroup::with([
            'settings',
        ])->orderBy('order', 'ASC')->get();

        return view('settings.list')->with([
            'groups' => $settings,
        ]);
    }

    /**
     *
     * @return RedirectResponse|View
     */
    public function edit(int $id)
    {
        $setting = Setting::find($id);

        if (is_null($setting)) {
            return redirect(route('settings.index', []))
                ->with([
                    'errors' => collect([__('app.alert.error.not_exist')]),
                ]);
        }

        // 只读项（如 version）仍然 404；集成配置走白名单，可以编辑
        if (! Setting::isEditable($setting)) {
            return abort(404);
        }

        return view('settings.edit')->with([
            'setting' => $setting,
            'value' => $setting->value,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $setting = Setting::find($id);
        $user = $this->user();
        $route = route('settings.index', []);

        try {
            if (is_null($setting)) {
                throw new Exception('not_exists');
            }

            if ($setting->type === 'image') {
                $validatedData = $request->validate([
                    'value' => 'image',
                ]);

                if (!$request->hasFile('value')) {
                    throw new \Exception('file_too_big');
                }

                $image = $request->file('value');
                $extension = $image->getClientOriginalExtension();

                if ($extension === 'svg') {
                    $sanitizer = new Sanitizer();
                    $sanitizedSvg = $sanitizer->sanitize(file_get_contents($image->getRealPath()));

                    // Verify that the sanitization removed malicious content
                    if (strpos($sanitizedSvg, '<script>') !== false) {
                        throw new \Exception('SVG contains malicious content and cannot be uploaded.');
                    }

                    // Save the sanitized SVG back to the file
                    file_put_contents($image->getRealPath(), $sanitizedSvg);
                }

                $path = $image->store('backgrounds', 'public');

                if ($path === null) {
                    throw new \Exception('file_not_stored');
                }

                $setting_value = $path;
            } else {
                $data = Setting::getInput($request);
                $setting_value = $data->value;
            }

            // 密钥框留空 = 保持原值，避免误把已存的密钥清掉
            if ($setting->type === 'password' && (string) $setting_value === '') {
                $setting_value = $setting->value;
            }

            if ((bool) $setting->system === true) {
                // 系统级设置：写全局值，避免每个用户各存一份
                $setting->value = $setting_value;
                $setting->save();
            } else {
                $user->settings()->detach($setting->id);
                $user->settings()->save($setting, ['uservalue' => $setting_value]);
            }

            // 行内保存：不跳页，直接把新的显示值返回给前端
            if ($request->wantsJson()) {
                $fresh = Setting::find($setting->id);
                $display = $fresh->list_value;
                $html = $fresh->type === 'textarea'
                    ? '<pre class="setting-value">' . $display . '</pre>'
                    : '<span class="setting-value">' . $display . '</span>';

                return response()->json([
                    'ok' => true,
                    'display_html' => $html,
                    'message' => __('app.alert.success.setting_updated'),
                ]);
            }

            // 保存后回到刚才那个分组（列表页按 #group-N 恢复 tab）
            $target = $route . ($setting->group_id ? '#group-' . $setting->group_id : '');

            return redirect($target)
                ->with([
                    'success' => __('app.alert.success.setting_updated'),
                ]);
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => __('app.alert.error.' . $e->getMessage()),
                ], 422);
            }

            return redirect($route)
                ->with([
                    'errors' => collect([__('app.alert.error.' . $e->getMessage())]),
                ]);
        }
    }

    public function clear(int $id): RedirectResponse
    {
        $user = $this->user();
        $setting = Setting::find($id);
        if ((bool) $setting->system !== true) {
            $user->settings()->detach($setting->id);
            $user->settings()->save($setting, ['uservalue' => '']);
        }
        $route = route('settings.index', []);

        return redirect($route)
        ->with([
            'success' => __('app.alert.success.setting_updated'),
        ]);
    }

    public function search(Request $request)
    {
    }
}
