<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\MedicineType;

/**
 * One medicine: one brand in one strength and dosage form. A value no source gives is null.
 *
 * `raw` holds the whole object as the API sent it, fields this version does not know included.
 */
readonly class Medicine
{
    public string $id;
    public string $uuid;
    public string $brand;
    public ?string $category;
    public string $productName;
    public ?string $productImage;

    /** @var list<string> */
    public array $images;
    public ?string $unit;
    public ?string $size;
    public ?float $price;
    public ?string $pricePer;
    public ?string $priceSource;

    /** @var list<UnitPrice> */
    public array $unitPrices;
    public Prices $prices;
    public ?string $descriptionEn;
    public ?string $descriptionBn;
    public ?string $dosageForm;
    public ?string $strength;
    public ?string $genericName;
    public ?string $manufacturer;
    public ?string $sourceUrl;
    public string $lastUpdated;
    public bool $dgdaListed;
    public ?MedicineType $medicineType;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $type = Data::nullableString($data, 'medicine_type');

        $this->id = Data::string($data, 'id');
        $this->uuid = Data::string($data, 'uuid');
        $this->brand = Data::string($data, 'brand');
        $this->category = Data::nullableString($data, 'category');
        $this->productName = Data::string($data, 'product_name');
        $this->productImage = Data::nullableString($data, 'product_image');
        $this->images = Data::strings($data, 'images');
        $this->unit = Data::nullableString($data, 'unit');
        $this->size = Data::nullableString($data, 'size');
        $this->price = Data::nullableNumber($data, 'price');
        $this->pricePer = Data::nullableString($data, 'price_per');
        $this->priceSource = Data::nullableString($data, 'price_source');
        $this->unitPrices = Data::objects($data, 'unit_prices', static fn(array $item) => new UnitPrice($item));
        $this->prices = new Prices(Data::object($data, 'prices'));
        $this->descriptionEn = Data::nullableString($data, 'description_en');
        $this->descriptionBn = Data::nullableString($data, 'description_bn');
        $this->dosageForm = Data::nullableString($data, 'dosage_form');
        $this->strength = Data::nullableString($data, 'strength');
        $this->genericName = Data::nullableString($data, 'generic_name');
        $this->manufacturer = Data::nullableString($data, 'manufacturer');
        $this->sourceUrl = Data::nullableString($data, 'source_url');
        $this->lastUpdated = Data::string($data, 'last_updated');
        $this->dgdaListed = Data::bool($data, 'dgda_listed');
        // tryFrom: a register added later reads as null here and stays in `raw`.
        $this->medicineType = $type === null ? null : MedicineType::tryFrom($type);
        $this->raw = $data;
    }
}
