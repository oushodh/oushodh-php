<?php

declare(strict_types=1);

namespace Oushodh\Exception;

/**
 * 401 unauthorized: no key, an unknown key or a revoked key.
 */
final class AuthenticationException extends OushodhException {}
