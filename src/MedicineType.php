<?php

declare(strict_types=1);

namespace Oushodh;

/**
 * The DGDA register that lists a medicine.
 */
enum MedicineType: string
{
    case Allopathic = 'allopathic';
    case Homoeopathic = 'homoeopathic';
    case Herbal = 'herbal';
    case Ayurvedic = 'ayurvedic';
    case Unani = 'unani';
}
