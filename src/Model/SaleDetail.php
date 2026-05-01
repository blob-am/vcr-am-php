<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Detail payload returned by {@see \BlobSolutions\VcrAm\VcrClient::getSale()}.
 *
 * `createdAt` is an ISO 8601 datetime string in UTC (e.g.
 * `2026-05-02T08:30:00.000Z`) — left as a string in the SDK so callers can
 * parse with the date library of their choice (`DateTimeImmutable`,
 * Carbon, etc.) without forcing a specific dependency.
 *
 * `receipt` is nullable because SRC issuance may have failed at the time
 * the sale was registered — see {@see Receipt::$fiscal} for partial states.
 */
final readonly class SaleDetail
{
    /**
     * @param list<SaleRefund> $refunds
     * @param list<SaleItem>   $items
     */
    public function __construct(
        public int $id,
        public string $createdAt,
        public ?string $buyerTin,
        public float $cashAmount,
        public float $nonCashAmount,
        public float $prepaymentAmount,
        public float $compensationAmount,
        public ?Receipt $receipt,
        public array $refunds,
        public SaleCashier $cashier,
        public array $items,
    ) {
    }
}
