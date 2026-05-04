<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Unit;
use InvalidArgumentException;
use JsonSerializable;

/**
 * One line item in a sale receipt.
 *
 * Decimal fields (`quantity`, `price`, `totalAmountTolerance`) are passed as
 * strings to preserve precision over the wire — consistent with the
 * TypeScript SDK and the underlying Prisma `Decimal` type.
 *
 * `emarks` carries identifiers of excise marks consumed by this line item
 * (alcohol, tobacco, pharmaceuticals — Govt Decision 1976-N, effective
 * 2026-05-01). Per-item by domain design — the wire format flattens them
 * into a single top-level array per receipt at the VCR API boundary, but
 * the SDK preserves item-level grouping so refund flows can correctly
 * subset codes against the original sale. Omit the field for unmarked
 * goods.
 */
final readonly class SaleItem implements JsonSerializable
{
    /**
     * @param ?list<string> $emarks
     */
    public function __construct(
        public Offer $offer,
        public Department $department,
        public string $quantity,
        public string $price,
        public Unit $unit,
        public ?Discounts $discounts = null,
        public ?string $totalAmountTolerance = null,
        public ?array $emarks = null,
    ) {
        if (preg_match('/^\d+(\.\d+)?$/', $quantity) !== 1) {
            throw new InvalidArgumentException('quantity must be a non-negative decimal string (e.g. "1" or "1.500").');
        }

        if (preg_match('/^\d+(\.\d+)?$/', $price) !== 1) {
            throw new InvalidArgumentException('price must be a non-negative decimal string (e.g. "750" or "750.00").');
        }

        if ($totalAmountTolerance !== null && preg_match('/^\d+(\.\d+)?$/', $totalAmountTolerance) !== 1) {
            throw new InvalidArgumentException('totalAmountTolerance must be a non-negative decimal string (e.g. "0.01"). Omit the field for an exact match.');
        }

        if ($emarks !== null) {
            foreach ($emarks as $emark) {
                if (trim($emark) === '') {
                    throw new InvalidArgumentException('emarks entries must not be empty.');
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'offer' => $this->offer,
            'department' => $this->department,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'unit' => $this->unit->value,
        ];

        if ($this->discounts !== null) {
            $payload['discounts'] = $this->discounts;
        }

        if ($this->totalAmountTolerance !== null) {
            $payload['totalAmountTolerance'] = $this->totalAmountTolerance;
        }

        if ($this->emarks !== null) {
            $payload['emarks'] = $this->emarks;
        }

        return $payload;
    }
}
