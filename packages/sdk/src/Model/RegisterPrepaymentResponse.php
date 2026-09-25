<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerPrepayment()}.
 *
 * `crn` and `fiscal` are nullable because SRC fiscal issuance can be
 * pending at the time the prepayment is recorded (the SDK has already
 * persisted the prepayment in our database; the SRC handshake may retry
 * asynchronously).
 *
 * `receiptUrl` is the buyer-facing receipt page, safe to email or print as a
 * QR — it carries no account access. Nullable rather than required: the field
 * was added to the API after this model shipped, and a convenience link must
 * never be the reason a fiscal response fails to parse. Build it from `crn`
 * and `urlId` if you need a fallback.
 */
final readonly class RegisterPrepaymentResponse
{
    public function __construct(
        public string $urlId,
        public int $prepaymentId,
        public ?string $crn,
        public int $receiptId,
        public ?string $fiscal,
        public ?string $receiptUrl = null,
    ) {
    }
}
