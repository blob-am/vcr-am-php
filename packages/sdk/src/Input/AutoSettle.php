<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\AutoSettleTender;
use JsonSerializable;

/**
 * Derived-total payment for a sale: name a single tender and the VCR settles
 * the whole server-computed AMD cart total on it. Mutually exclusive with
 * {@see SaleAmount} — a {@see RegisterSaleInput} carries exactly one of the two.
 *
 * This is the natural (and only) payment mode for a foreign-currency sale,
 * where the AMD total is not knowable client-side until the server converts
 * each line at the CBA rate. It is equally useful for a plain AMD sale that
 * would rather not sum the cart itself (zero-tap checkout).
 */
final readonly class AutoSettle implements JsonSerializable
{
    public function __construct(
        public AutoSettleTender $tender,
    ) {
    }

    /**
     * @return array{tender: string}
     */
    public function jsonSerialize(): array
    {
        return ['tender' => $this->tender->value];
    }
}
