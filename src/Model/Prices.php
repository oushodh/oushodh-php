<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Data;

/**
 * Every MRP the sources state for a medicine: per unit, per strip and per pack. Amounts are in BDT.
 */
final readonly class Prices
{
    public string $currency;
    public ?PriceAmount $unit;

    /** @var list<SizePrice> */
    public array $sizes;
    public ?StripPrice $strip;

    /** @var list<PackPrice> */
    public array $packs;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $unit = Data::nullableObject($data, 'unit');
        $strip = Data::nullableObject($data, 'strip');

        $this->currency = Data::string($data, 'currency');
        $this->unit = $unit === null ? null : new PriceAmount($unit);
        $this->sizes = Data::objects($data, 'sizes', static fn(array $item) => new SizePrice($item));
        $this->strip = $strip === null ? null : new StripPrice($strip);
        $this->packs = Data::objects($data, 'packs', static fn(array $item) => new PackPrice($item));
        $this->raw = $data;
    }
}
