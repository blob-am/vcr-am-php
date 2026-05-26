<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * A single open prepayment contributing to a customer's balance — one row
 * in {@see CustomerPrepaymentBalance::$openPrepayments}.
 *
 * Listed in FIFO order, mirroring the order `applyPrepaymentToSale` will
 * consume them. `remaining` is the leftover credit on this specific receipt
 * after any applies / refunds; the receipt's original totals are kept in
 * `cashAmount` / `nonCashAmount` for reconciliation.
 */
final readonly class CustomerOpenPrepayment
{
    public function __construct(
        public int $prepaymentId,
        public string $createdAt,
        public float $cashAmount,
        public float $nonCashAmount,
        public ?string $buyerTin,
        public float $remaining,
    ) {
    }
}
