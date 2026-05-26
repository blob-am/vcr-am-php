<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Customer-side balance summary returned by
 * {@see \BlobSolutions\VcrAm\VcrClient::getCustomerPrepaymentBalance()}.
 *
 * Scoped to the BusinessEntity that owns the calling VCR's API key — so a
 * merchant running multiple VCRs under one TIN sees a single wallet rather
 * than per-VCR slices. `balance` sums the remaining credit across every
 * matching open prepayment; `openPrepayments` enumerates them in FIFO order.
 */
final readonly class CustomerPrepaymentBalance
{
    /**
     * @param list<CustomerOpenPrepayment> $openPrepayments
     */
    public function __construct(
        public int $entityId,
        public string $customerRef,
        public float $balance,
        public array $openPrepayments,
    ) {
    }
}
