<?php

namespace App\Enums;

/**
 * Normalized lifecycle states for SMS delivery attempts.
 *
 * @since 1.2.0
 */
enum SmsMessageStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
