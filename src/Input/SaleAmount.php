<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * How the buyer paid for the sale. At least one of the four buckets must be
 * set — the API rejects sales with no payment basis.
 *
 * Values are decimal-as-string for precision parity with the wire format.
 */
final readonly class SaleAmount implements JsonSerializable
{
    public function __construct(
        public ?string $prepayment = null,
        public ?string $compensation = null,
        public ?string $nonCash = null,
        public ?string $cash = null,
    ) {
        if ($prepayment === null && $compensation === null && $nonCash === null && $cash === null) {
            throw new InvalidArgumentException(
                'SaleAmount requires at least one of: prepayment, compensation, nonCash, cash.',
            );
        }

        foreach (['prepayment' => $prepayment, 'compensation' => $compensation, 'nonCash' => $nonCash, 'cash' => $cash] as $name => $value) {
            if ($value !== null && ! self::isDecimalString($value)) {
                throw new InvalidArgumentException(sprintf('%s must be a non-negative decimal string (e.g. "1500" or "1500.00").', $name));
            }
        }
    }

    /**
     * Decimal string in the format expected by the SRC wire protocol:
     * non-empty, digits only with at most one decimal point, no sign,
     * no scientific notation. Mirrors the precision contract of the
     * TypeScript SDK's `z.string().regex(/^\d+(\.\d+)?$/)` shape.
     */
    private static function isDecimalString(string $value): bool
    {
        return preg_match('/^\d+(\.\d+)?$/', $value) === 1;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        $payload = [];

        if ($this->prepayment !== null) {
            $payload['prepayment'] = $this->prepayment;
        }

        if ($this->compensation !== null) {
            $payload['compensation'] = $this->compensation;
        }

        if ($this->nonCash !== null) {
            $payload['nonCash'] = $this->nonCash;
        }

        if ($this->cash !== null) {
            $payload['cash'] = $this->cash;
        }

        return $payload;
    }
}
