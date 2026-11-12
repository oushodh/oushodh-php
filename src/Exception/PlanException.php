<?php

declare(strict_types=1);

namespace Oushodh\Exception;

/**
 * 403 plan_expired (no active plan) or plan_limit (the medicine is beyond your plan's medicines).
 */
final class PlanException extends OushodhException {}
