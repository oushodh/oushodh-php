<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The unit price in one pack size, from `unit_prices`. Kept for older clients: read `Prices::$sizes`.
 */
final readonly class UnitPrice
{
    public ?string $size;
    public float $price;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->size = Data::nullableString($data, 'size');
        $this->price = Data::number($data, 'price');
        $this->raw = $data;
    }
}
