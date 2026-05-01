<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\TaxRegime;

/**
 * Department as embedded in a sale detail response — carries the tax regime
 * and localised title at the time the sale was registered.
 */
final readonly class SaleDepartment
{
    /**
     * @param list<LocalizationEntry> $title
     */
    public function __construct(
        public int $internalId,
        public TaxRegime $taxRegime,
        public array $title,
    ) {
    }
}
