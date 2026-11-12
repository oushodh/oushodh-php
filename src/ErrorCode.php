<?php

declare(strict_types=1);

namespace Oushodh;

/**
 * The `code` of an error body. Match on it, never on the message.
 */
enum ErrorCode: string
{
    case BadRequest = 'bad_request';
    case Unauthorized = 'unauthorized';
    case PlanExpired = 'plan_expired';
    case PlanLimit = 'plan_limit';
    case NotFound = 'not_found';
    case RateLimited = 'rate_limited';
    case QuotaExceeded = 'quota_exceeded';
    case Internal = 'internal';
}
