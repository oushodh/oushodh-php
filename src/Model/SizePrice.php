<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The unit price in one pack size, from `prices.sizes`.
 */
final readonly class SizePrice
{
    public ?string $size;
    public float $amount;
    public string $source;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->size = Data::nullableString($data, 'size');
        $this->amount = Data::number($data, 'amount');
        $this->source = Data::string($data, 'source');
        $this->raw = $data;
    }
}
