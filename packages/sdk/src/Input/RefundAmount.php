<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * How the refund is paid back to the buyer. At least one of `cash`, `nonCash`
 * or `prepayment` must be set. `prepayment` is the part of the sale's used
 * prepayment coming back. Decimal-as-string for precision parity with the wire
 * format (and with {@see SaleAmount}).
 */
final readonly class RefundAmount implements JsonSerializable
{
    public function __construct(
        public ?string $cash = null,
        public ?string $nonCash = null,
        public ?string $prepayment = null,
    ) {
        if ($cash === null && $nonCash === null && $prepayment === null) {
            throw new InvalidArgumentException(
                'RefundAmount requires at least one of: cash, nonCash, prepayment.',
            );
        }

        if ($cash !== null && preg_match('/^\d+(\.\d+)?$/', $cash) !== 1) {
            throw new InvalidArgumentException('cash must be a non-negative decimal string (e.g. "5000" or "5000.00").');
        }

        if ($nonCash !== null && preg_match('/^\d+(\.\d+)?$/', $nonCash) !== 1) {
            throw new InvalidArgumentException('nonCash must be a non-negative decimal string (e.g. "5000" or "5000.00").');
        }

        if ($prepayment !== null && preg_match('/^\d+(\.\d+)?$/', $prepayment) !== 1) {
            throw new InvalidArgumentException('prepayment must be a non-negative decimal string (e.g. "5000" or "5000.00").');
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

        if ($this->prepayment !== null) {
            $payload['prepayment'] = $this->prepayment;
        }

        return $payload;
    }
}
