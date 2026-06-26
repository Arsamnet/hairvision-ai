<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Displays and updates database-backed platform settings.
 *
 * @since 1.0.0
 */
class SystemSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.system.settings.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'settings' => SystemSetting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, SystemSettingService $settings): RedirectResponse
    {
        $submitted = $request->validate([
            'settings' => ['required', 'array'],
        ])['settings'];

        foreach (SystemSetting::query()->get() as $setting) {
            if (! array_key_exists($setting->key, $submitted)) {
                continue;
            }

            Validator::make(
                ['value' => $submitted[$setting->key]],
                ['value' => $this->ruleFor($setting)]
            )->validate();

            $settings->set($setting->key, $submitted[$setting->key]);
        }

        return back()->with('status', __('settings.saved'));
    }

    /**
     * Return validation rules for a setting type.
     *
     * @return array<int, string>
     */
    private function ruleFor(SystemSetting $setting): array
    {
        return match ($setting->type) {
            'integer' => ['required', 'integer', 'min:1', 'max:1000'],
            'boolean' => ['required', 'boolean'],
            default => ['nullable', 'string', 'max:500'],
        };
    }
}
