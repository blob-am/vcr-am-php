<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerSale()}.
 *
 * `urlId` is a public-facing receipt URL slug (e.g. shareable to the buyer),
 * `crn` is the cash-register-number assigned by SRC, and `fiscal` is the
 * fiscal serial issued by the State Revenue Committee.
 *
 * `receiptUrl` is the buyer-facing receipt page, safe to email or print as a
 * QR — it carries no account access. Nullable rather than required: the field
 * was added to the API after this model shipped, and a convenience link must
 * never be the reason a fiscal response fails to parse. Build it from `crn`
 * and `urlId` if you need a fallback.
 */
final readonly class RegisterSaleResponse
{
    public function __construct(
        public string $urlId,
        public int $saleId,
        public string $crn,
        public int $srcReceiptId,
        public string $fiscal,
        public ?string $receiptUrl = null,
    ) {
    }
}
