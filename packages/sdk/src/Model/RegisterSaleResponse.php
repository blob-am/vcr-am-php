<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerSale()}.
 *
 * `urlId` is a public-facing receipt URL slug (e.g. shareable to the buyer),
 * `crn` is the cash-register-number assigned by SRC, and `fiscal` is the
 * fiscal serial issued by the State Revenue Committee.
 */
final readonly class RegisterSaleResponse
{
    public function __construct(
        public string $urlId,
        public int $saleId,
        public string $crn,
        public int $srcReceiptId,
        public string $fiscal,
    ) {
    }
}
