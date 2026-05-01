<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\AdditionalDiscountType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Secondary "additional" discount applied on top of {@see BaseDiscount} —
 * see {@see Discounts::$additional}. Excludes per-unit price discounts.
 */
final readonly class AdditionalDiscount implements JsonSerializable
{
    public function __construct(
        public AdditionalDiscountType $type,
        public string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Discount value must not be empty.');
        }
    }

    /**
     * @return array{type: string, value: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'value' => $this->value,
        ];
    }
}
