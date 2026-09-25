<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerPrepaymentRefund()}.
 *
 * `crn` and `fiscal` are nullable for the same reason as
 * {@see RegisterPrepaymentResponse}: SRC fiscal issuance may be pending.
 *
 * `receiptUrl` is the buyer-facing receipt page, safe to email or print as a
 * QR — it carries no account access. Nullable rather than required: the field
 * was added to the API after this model shipped, and a convenience link must
 * never be the reason a fiscal response fails to parse. Build it from `crn`
 * and `urlId` if you need a fallback.
 */
final readonly class RegisterPrepaymentRefundResponse
{
    public function __construct(
        public string $urlId,
        public int $prepaymentRefundId,
        public ?string $crn,
        public int $receiptId,
        public ?string $fiscal,
        public ?string $receiptUrl = null,
    ) {
    }
}
