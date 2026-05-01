<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\OfferType;

/**
 * Offer (product or service) as embedded in a sale detail response.
 */
final readonly class SaleOffer
{
    /**
     * @param list<LocalizationEntry> $title
     */
    public function __construct(
        public OfferType $type,
        public string $classifierCode,
        public array $title,
    ) {
    }
}
