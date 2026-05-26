<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\PrepaymentState;

/**
 * One entry in the result of {@see \BlobSolutions\VcrAm\VcrClient::listPrepayments()}.
 *
 * Lighter than {@see PrepaymentDetail} — no receipt, no refund, no cashier —
 * because the list endpoint is meant for browsing and reconciliation, not for
 * receipt rendering. Call `getPrepayment($id)` to drill into a specific row.
 */
final readonly class PrepaymentListItem
{
    public function __construct(
        public int $id,
        public string $createdAt,
        public ?string $buyerTin,
        public float $cashAmount,
        public float $nonCashAmount,
        public float $remaining,
        public PrepaymentState $state,
    ) {
    }
}
