<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * The department an {@see OfferListItem} defaults its sale lines to, as
 * returned by the catalogue read endpoints. Only the internal id is exposed —
 * it is the value a sale item's `department.id` references.
 */
final readonly class OfferDefaultDepartment
{
    public function __construct(
        public int $internalId,
    ) {
    }
}
