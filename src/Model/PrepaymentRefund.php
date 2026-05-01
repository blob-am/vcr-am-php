<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Refund issued against a prepayment, as embedded in
 * {@see PrepaymentDetail::$refund}. Distinct from {@see SaleRefund} —
 * prepayment refunds are atomic and have no per-item breakdown (a
 * prepayment is a single advance amount, not a basket of goods).
 *
 * `receipt` is nullable because SRC fiscal issuance can be pending.
 */
final readonly class PrepaymentRefund
{
    public function __construct(
        public float $nonCashAmount,
        public float $cashAmount,
        public ?Receipt $receipt,
    ) {
    }
}
