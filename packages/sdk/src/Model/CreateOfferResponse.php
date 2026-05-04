<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::createOffer()}.
 *
 * Only the new offer's internal numeric id is returned. To fetch the
 * stored representation, the offer needs to be referenced from a sale or
 * read via the catalogue endpoints (not yet exposed by this SDK).
 */
final readonly class CreateOfferResponse
{
    public function __construct(
        public int $offerId,
    ) {
    }
}
