<?php

declare(strict_types=1);

namespace Oushodh\Exception;

use Oushodh\ErrorCode;

/**
 * The base of every error the SDK throws.
 *
 * `status` is the HTTP status and `errorCode` the `code` of the error body. Both are null when no
 * answer came back. `errorCode` is also null for a code this version does not know.
 */
class OushodhException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly ?ErrorCode $errorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
