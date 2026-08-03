<?php

namespace App\Support\Analytics;

/**
 * Defines the allowlisted, PII-free events in the public landing conversion funnel.
 *
 * @since 2.1.0
 */
final class LandingEventCatalog
{
    public const APPLICATION_CLICKED = 'application_clicked';

    public const PHOTO_SELECTED = 'photo_selected';

    public const PHONE_SUBMITTED = 'phone_submitted';

    public const OTP_VERIFIED = 'otp_verified';

    public const PHOTO_UPLOADED = 'photo_uploaded';

    public const GENERATION_REQUESTED = 'generation_requested';

    public const RESULT_VIEWED = 'result_viewed';

    /**
     * Return every event accepted from the public landing client.
     *
     * @return array<int, string> Stable event type codes.
     *
     * @since 2.1.0
     */
    public static function all(): array
    {
        return [
            self::APPLICATION_CLICKED,
            self::PHOTO_SELECTED,
            self::PHONE_SUBMITTED,
            self::OTP_VERIFIED,
            self::PHOTO_UPLOADED,
            self::GENERATION_REQUESTED,
            self::RESULT_VIEWED,
        ];
    }

    /**
     * Return the ordered funnel stages after the landing page view.
     *
     * @return array<int, string> Event types in conversion order.
     *
     * @since 2.1.0
     */
    public static function funnel(): array
    {
        return self::all();
    }
}
