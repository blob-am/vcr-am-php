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
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf('%s must not be empty when provided.', $name));
            }
        }
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
