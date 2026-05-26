<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\PrepaymentState;

/**
 * Detail payload returned by {@see \BlobSolutions\VcrAm\VcrClient::getPrepayment()}.
 *
 * Unlike {@see SaleDetail}, a prepayment has no items (it represents a
 * lump-sum advance, not a basket of goods) and at most one refund (a
 * prepayment is refunded in full, not partially).
 *
 * `createdAt` is an ISO 8601 datetime string in UTC — left as a string
 * so callers can parse with the date library of their choice.
 *
 * `remaining` and `state` are derived from the PrepaymentLedger and reflect
 * the prepayment's current lifecycle. Treat them as a current snapshot — do
 * not cache.
 */
final readonly class PrepaymentDetail
{
    public function __construct(
        public int $id,
        public string $createdAt,
        public ?string $buyerTin,
        public float $cashAmount,
        public float $nonCashAmount,
        public ?Receipt $receipt,
        public ?PrepaymentRefund $refund,
        public Cashier $cashier,
        public float $remaining,
        public PrepaymentState $state,
    ) {
    }
}
