<?php

namespace App\Support;

/**
 * Normalizes local dashboard icon codes before storage or rendering.
 *
 * @since 1.0.0
 */
final class AdminIcon
{
    public const DEFAULT_CATEGORY_ICON = 'sparkles';

    /**
     * Semantic application icon names mapped to local UIcons codes.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'layout-dashboard' => 'dashboard-panel',
        'users' => 'users',
        'role' => 'user-key',
        'permission' => 'key',
        'security' => 'shield',
        'shield' => 'shield',
        'building' => 'building',
        'calendar' => 'calendar',
        'contact' => 'address-book',
        'finance' => 'wallet',
        'credit-card' => 'credit-card',
        'message' => 'message-sms',
        'chart' => 'chart-histogram',
        'report-user' => 'chart-user',
        'report-credit' => 'chart-pie-alt',
        'report-management' => 'chart-line-up',
        'settings' => 'settings-sliders',
        'search' => 'search',
        'filter' => 'filter',
        'bookmark' => 'bookmark',
        'help-center' => 'life-ring',
        'guide' => 'book-open-reader',
        'faq' => 'message-question',
        'bell' => 'bell-ring',
        'link' => 'link',
        'lock' => 'lock',
        'sparkles' => 'sparkles',
        'package' => 'box-open',
        'Z' => 'sparkles',
        'menu-dots-vertical' => 'menu-dots-vertical',
        'eye' => 'eye',
        'edit' => 'edit',
        'trash' => 'trash',
        'x' => 'cross-small',
        'chevron-down' => 'angle-small-down',
        'chevron-left' => 'angle-small-left',
        'chevron-right' => 'angle-small-right',
        'sort-alt' => 'sort-alt',
        'arrow-up' => 'arrow-up',
        'arrow-down' => 'arrow-down',
        'plus' => 'plus',
        'minus' => 'minus',
        'check' => 'check',
        'upload' => 'upload',
        'image' => 'add-image',
        'mobile' => 'mobile-button',
        'otp' => 'otp',
        'gallery' => 'images',
        'template' => 'palette',
        'category' => 'category',
        'ai-applications' => 'apps',
        'ai-model' => 'robot',
        'archive' => 'bookmark',
        'zoom-in' => 'zoom-in',
        'magic-wand' => 'magic-wand',
        'login' => 'arrow-right-to-bracket',
        'invoice' => 'file-invoice',
        'night-day' => 'night-day',
        'moon' => 'night-day',
        'sun' => 'night-day',
    ];

    /**
     * @var array<int, string>
     */
    private const AVAILABLE_CODES = [
        'add-image',
        'address-book',
        'angle-small-down',
        'angle-small-left',
        'angle-small-right',
        'apps',
        'arrow-down',
        'arrow-right-to-bracket',
        'arrow-small-left',
        'arrow-small-right',
        'arrow-up',
        'bell-ring',
        'bookmark',
        'book-alt',
        'book-open-reader',
        'box-open',
        'building',
        'calendar',
        'category',
        'chart-histogram',
        'chart-line-up',
        'chart-pie-alt',
        'chart-user',
        'check',
        'credit-card',
        'cross-small',
        'dashboard-panel',
        'edit',
        'eye',
        'file-invoice',
        'filter',
        'images',
        'key',
        'link',
        'life-ring',
        'lock',
        'magic-wand',
        'menu-dots-vertical',
        'message-sms',
        'message-question',
        'minus',
        'mobile-button',
        'night-day',
        'otp',
        'palette',
        'plus',
        'robot',
        'search',
        'settings-sliders',
        'shield',
        'sort-alt',
        'sparkles',
        'trash',
        'upload',
        'user-key',
        'users',
        'wallet',
        'zoom-in',
    ];

    /**
     * Resolve an icon name to its local class list.
     *
     * @param  mixed  $icon  The icon value.
     * @param  string  $fallback  The fallback value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function resolve(mixed $icon, string $fallback = 'plus'): string
    {
        if (is_string($icon)) {
            $name = trim($icon);

            if (isset(self::ALIASES[$name])) {
                return self::ALIASES[$name];
            }

            $code = self::normalizeForStorage($name);

            if ($code !== null) {
                return $code;
            }
        }

        if (isset(self::ALIASES[$fallback])) {
            return self::ALIASES[$fallback];
        }

        return self::normalizeForStorage($fallback) ?? 'plus';
    }

    /**
     * Execute the aliases operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public static function aliases(): array
    {
        return self::ALIASES;
    }

    /**
     * Execute the available codes operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    public static function availableCodes(): array
    {
        return self::AVAILABLE_CODES;
    }

    /**
     * Normalize an icon name for lookup.
     *
     * @param  mixed  $icon  The icon value.
     * @param  string  $fallback  The fallback value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public static function normalize(mixed $icon, string $fallback = self::DEFAULT_CATEGORY_ICON): string
    {
        $code = self::normalizeForStorage($icon);

        if ($code !== null) {
            return $code;
        }

        return in_array($fallback, self::AVAILABLE_CODES, true) ? $fallback : self::DEFAULT_CATEGORY_ICON;
    }

    /**
     * Normalize for storage.
     *
     * @param  mixed  $icon  The icon value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public static function normalizeForStorage(mixed $icon): ?string
    {
        if (! is_string($icon)) {
            return null;
        }

        $code = trim(strtolower($icon));
        $code = preg_replace('/\s+/', '-', $code) ?? '';
        $code = preg_replace('/^fi\s+/', '', $code) ?? '';
        $code = preg_replace('/^fi-rr-/', '', $code) ?? '';
        $code = str_replace('_', '-', $code);

        if (! preg_match('/^[a-z0-9-]+$/', $code)) {
            return null;
        }

        return in_array($code, self::AVAILABLE_CODES, true) ? $code : null;
    }
}
