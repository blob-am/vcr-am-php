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
        if (trim($quantity) === '') {
            throw new InvalidArgumentException('quantity must not be empty.');
        }

        if (trim($price) === '') {
            throw new InvalidArgumentException('price must not be empty.');
        }

        if ($totalAmountTolerance !== null && trim($totalAmountTolerance) === '') {
            throw new InvalidArgumentException('totalAmountTolerance must not be empty when provided. Omit the field instead.');
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
