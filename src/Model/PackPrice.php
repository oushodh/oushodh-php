<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The MRP of one pack or box, from `prices.packs`. `units` is null when the source does not say.
 */
final readonly class PackPrice
{
    public float $amount;
    public ?float $units;
    public string $label;
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
        $this->label = Data::string($data, 'label');
        $this->source = Data::string($data, 'source');
        $this->raw = $data;
    }
}
