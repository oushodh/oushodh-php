<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Data;

/**
 * The medical text of a medicine, as its source wrote it, in English. Some text holds HTML tags.
 * It is information for professionals, not medical advice.
 */
final readonly class Monograph
{
    public string $source;
    public ?string $indications;
    public ?string $composition;
    public ?string $pharmacology;
    public ?string $dosage;
    public ?string $interactions;
    public ?string $contraindications;
    public ?string $sideEffects;
    public ?string $pregnancyLactation;
    public ?string $precautions;
    public ?string $overdose;
    public ?string $storage;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->source = Data::string($data, 'source');
        $this->indications = Data::nullableString($data, 'indications');
        $this->composition = Data::nullableString($data, 'composition');
        $this->pharmacology = Data::nullableString($data, 'pharmacology');
        $this->dosage = Data::nullableString($data, 'dosage');
        $this->interactions = Data::nullableString($data, 'interactions');
        $this->contraindications = Data::nullableString($data, 'contraindications');
        $this->sideEffects = Data::nullableString($data, 'side_effects');
        $this->pregnancyLactation = Data::nullableString($data, 'pregnancy_lactation');
        $this->precautions = Data::nullableString($data, 'precautions');
        $this->overdose = Data::nullableString($data, 'overdose');
        $this->storage = Data::nullableString($data, 'storage');
        $this->raw = $data;
    }
}
