<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Single line of a refund — only carries the refunded quantity. The API
 * returns this stripped-down shape because the offer/price details are
 * already on the parent sale item.
 */
final readonly class SaleRefundItem
{
    public function __construct(
        public float $quantity,
    ) {
    }
}
