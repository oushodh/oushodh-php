<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The MRP of one unit, from `prices.unit`.
 */
final readonly class PriceAmount
{
    public float $amount;
    public string $source;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->amount = Data::number($data, 'amount');
        $this->source = Data::string($data, 'source');
        $this->raw = $data;
    }
}
