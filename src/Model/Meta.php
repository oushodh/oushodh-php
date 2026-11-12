<?php

declare(strict_types=1);

namespace Oushodh\Model;

/**
 * The account the key belongs to, its ID profile, the medicines the key reads and the last import.
 */
final readonly class Meta
{
    public string $client;
    public string $idProfile;
    public int $medicines;
    public ?string $lastImport;

    /** @var array<string, mixed> */
    public array $raw;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->client = Data::string($data, 'client');
        $this->idProfile = Data::string($data, 'id_profile');
        $this->medicines = Data::int($data, 'medicines');
        $this->lastImport = Data::nullableString($data, 'last_import');
        $this->raw = $data;
    }
}
