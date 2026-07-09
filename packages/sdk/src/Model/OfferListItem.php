<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\OfferType;

/**
 * One offer as returned by the catalogue read endpoints
 * ({@see \BlobSolutions\VcrAm\VcrClient::listOffers()},
 * {@see \BlobSolutions\VcrAm\VcrClient::getOffer()},
 * {@see \BlobSolutions\VcrAm\VcrClient::updateOffer()}).
 *
 * `defaultMeasureUnit` is a plain string rather than the {@see \BlobSolutions\VcrAm\Unit}
 * enum, on purpose: the server echoes whatever unit is stored, so a legacy
 * value outside the current enum can never make the response fail to parse.
 * `archivedAt` is `null` for a live offer and an ISO-8601 timestamp once
 * archived; `title` is the list of per-language localisation entries (each with
 * its own database id), matching the detail endpoints.
 */
final readonly class OfferListItem
{
    /**
     * @param list<LocalizationEntry> $title
     */
    public function __construct(
        public int $id,
        public ?string $externalId,
        public OfferType $type,
        public string $classifierCode,
        public string $defaultMeasureUnit,
        public OfferDefaultDepartment $defaultDepartment,
        public array $title,
        public ?string $archivedAt,
        public string $createdAt,
    ) {
    }
}
