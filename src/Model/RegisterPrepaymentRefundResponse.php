<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerPrepaymentRefund()}.
 *
 * `crn` and `fiscal` are nullable for the same reason as
 * {@see RegisterPrepaymentResponse}: SRC fiscal issuance may be pending.
 */
final readonly class RegisterPrepaymentRefundResponse
{
    public function __construct(
        public string $urlId,
        public int $prepaymentRefundId,
        public ?string $crn,
        public int $receiptId,
        public ?string $fiscal,
    ) {
    }
}
