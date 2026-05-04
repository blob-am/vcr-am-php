<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * One refund issued against a sale. Receipt is nullable because the SRC
 * fiscal endpoint may have failed at issue time even though the refund
 * itself was recorded in our database.
 */
final readonly class SaleRefund
{
    /**
     * @param list<SaleRefundItem> $items
     */
    public function __construct(
        public float $nonCashAmount,
        public float $cashAmount,
        public ?Receipt $receipt,
        public array $items,
    ) {
    }
}
