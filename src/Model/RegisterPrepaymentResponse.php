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
 */
final readonly class RegisterPrepaymentResponse
{
    public function __construct(
        public string $urlId,
        public int $prepaymentId,
        public ?string $crn,
        public int $receiptId,
        public ?string $fiscal,
    ) {
    }
}
