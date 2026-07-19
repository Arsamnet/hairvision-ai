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
     * @var array<int, string>
     */
    private const AVAILABLE_CODES = [
        'add-image',
        'address-book',
        'angle-small-down',
        'angle-small-left',
        'angle-small-right',
        'arrow-down',
        'arrow-right-to-bracket',
        'arrow-small-left',
        'arrow-small-right',
        'arrow-up',
        'bookmark',
        'box-open',
        'building',
        'calendar',
        'chart-histogram',
        'check',
        'credit-card',
        'cross-small',
        'dashboard-panel',
        'edit',
        'eye',
        'file-invoice',
        'filter',
        'images',
        'lock',
        'menu-dots-vertical',
        'message-sms',
        'mobile-button',
        'night-day',
        'otp',
        'plus',
        'search',
        'settings-sliders',
        'shield',
        'sort-alt',
        'sparkles',
        'trash',
        'upload',
        'users',
        'zoom-in',
    ];

    public static function normalize(mixed $icon, string $fallback = self::DEFAULT_CATEGORY_ICON): string
    {
        $code = self::normalizeForStorage($icon);

        if ($code !== null) {
            return $code;
        }

        return in_array($fallback, self::AVAILABLE_CODES, true) ? $fallback : self::DEFAULT_CATEGORY_ICON;
    }

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
