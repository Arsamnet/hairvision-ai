<?php

namespace App\Exceptions\Sms;

use RuntimeException;

/**
 * Raised when no active, accessible SMS provider can be resolved.
 *
 * @since 1.2.0
 */
class SmsProviderNotAvailableException extends RuntimeException {}
