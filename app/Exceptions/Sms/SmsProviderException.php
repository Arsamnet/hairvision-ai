<?php

namespace App\Exceptions\Sms;

use RuntimeException;

/**
 * Raised when a provider adapter cannot send an SMS.
 *
 * @since 1.2.0
 */
class SmsProviderException extends RuntimeException {}
