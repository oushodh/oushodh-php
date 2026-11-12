<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Data;

/**
 * The MRP of one strip, from `prices.strip`. `units` is null when the source does not say.
 */
final readonly class StripPrice
{
    public float $amount;
    public ?float $units;
    public string $source;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->amount = Data::number($data, 'amount');
        $this->units = Data::nullableNumber($data, 'units');
        $this->source = Data::string($data, 'source');
        $this->raw = $data;
    }
}
