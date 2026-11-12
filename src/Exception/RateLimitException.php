<?php

declare(strict_types=1);

namespace Oushodh\Exception;

use Oushodh\ErrorCode;

/**
 * 429 rate_limited (too many requests in a minute) or quota_exceeded (the month's requests are used up).
 *
 * `retryAfter` is the seconds the `Retry-After` header says to wait, or null when it is missing.
 */
final class RateLimitException extends OushodhException
{
    public function __construct(
        string $message,
        ?ErrorCode $errorCode,
        public readonly ?int $retryAfter,
    ) {
        parent::__construct($message, 429, $errorCode);
    }
}
