<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Fiscal receipt issued by Armenia's State Revenue Committee for a sale,
 * refund, prepayment, or prepayment refund. Returned embedded inside the
 * corresponding detail response.
 *
 * `time` is a Unix-style bigint timestamp serialised as a numeric string
 * (PHP int is not reliably 64-bit across platforms; the wire format keeps
 * it as a string for safety). Cast manually if you need an integer:
 * `(int) $receipt->time`.
 */
final readonly class Receipt
{
    public function __construct(
        public int $srcId,
        public string $time,
        public string $tin,
        public ?string $fiscal,
        public string $sn,
        public ?string $address,
        public float $total,
        public string $taxpayer,
        public float $change,
    ) {
    }
}
