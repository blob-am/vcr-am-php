<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * How a buyer paid for a prepayment. At least one of `cash` or `nonCash`
 * must be set. Decimal-as-string for precision parity with the wire format.
 *
 * Mirrors the structure of {@see RefundAmount} but the two are kept
 * distinct because their domain semantics differ (advance payment vs.
 * money returned to the buyer) — the TypeScript SDK keeps them separate
 * for the same reason.
 */
final readonly class PrepaymentAmount implements JsonSerializable
{
    public function __construct(
        public ?string $cash = null,
        public ?string $nonCash = null,
    ) {
        if ($cash === null && $nonCash === null) {
            throw new InvalidArgumentException(
                'PrepaymentAmount requires at least one of: cash, nonCash.',
            );
        }

        if ($cash !== null && preg_match('/^\d+(\.\d+)?$/', $cash) !== 1) {
            throw new InvalidArgumentException('cash must be a non-negative decimal string (e.g. "5000" or "5000.00").');
        }

        if ($nonCash !== null && preg_match('/^\d+(\.\d+)?$/', $nonCash) !== 1) {
            throw new InvalidArgumentException('nonCash must be a non-negative decimal string (e.g. "5000" or "5000.00").');
        }
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        $payload = [];

        if ($this->cash !== null) {
            $payload['cash'] = $this->cash;
        }

        if ($this->nonCash !== null) {
            $payload['nonCash'] = $this->nonCash;
        }

        return $payload;
    }
}
