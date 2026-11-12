<?php

declare(strict_types=1);

namespace Oushodh\Model;

use Oushodh\Data;

/**
 * One medicine with its medical text. `monograph` is null when no source gives the medicine any text.
 */
final readonly class MedicineDetail extends Medicine
{
    public ?Monograph $monograph;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $monograph = Data::nullableObject($data, 'monograph');

        $this->monograph = $monograph === null ? null : new Monograph($monograph);
    }
}
