<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::registerSaleRefund()}.
 *
 * `crn` and `fiscal` are nullable because SRC fiscal issuance can fail at
 * the time the refund is recorded — the refund itself is persisted in our
 * database either way; the SRC handshake retries asynchronously.
 */
final readonly class RegisterSaleRefundResponse
{
    public function __construct(
        public string $urlId,
        public int $saleRefundId,
        public ?string $crn,
        public int $receiptId,
        public ?string $fiscal,
    ) {
    }
}
