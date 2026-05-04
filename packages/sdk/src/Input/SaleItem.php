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
 */
final readonly class SaleItem implements JsonSerializable
{
    public function __construct(
        public Offer $offer,
        public Department $department,
        public string $quantity,
        public string $price,
        public Unit $unit,
        public ?Discounts $discounts = null,
        public ?string $totalAmountTolerance = null,
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

        return $payload;
    }
}
