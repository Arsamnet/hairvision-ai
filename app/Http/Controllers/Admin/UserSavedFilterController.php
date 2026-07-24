<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stores reusable list filter presets for the authenticated dashboard user.
 *
 * @since 1.0.0
 */
class UserSavedFilterController extends Controller
{
    /**
     * Persist a new user saved filter record.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'filter_key' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->savedFilters()->updateOrCreate(
            [
                'filter_key' => $validated['filter_key'],
                'name' => trim($validated['name']),
            ],
            [
                'filters_json' => $this->sanitizeFilters($validated['filters'] ?? []),
            ]
        );

        return back()->with('status', __('messages.filter_saved'));
    }

    /**
     * Execute the sanitize filters operation.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    private function sanitizeFilters(array $filters): array
    {
        $sanitized = [];

        foreach ($filters as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            if (! preg_match('/^[A-Za-z0-9_.-]+$/', $key) || in_array($key, ['_token', 'page'], true)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $sanitized[$key] = Str::limit($value, 500, '');
        }

        return $sanitized;
    }
}
