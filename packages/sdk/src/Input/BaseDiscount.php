<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\BaseDiscountType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Primary discount applied to a sale item — see {@see Discounts::$base}.
 */
final readonly class BaseDiscount implements JsonSerializable
{
    public function __construct(
        public BaseDiscountType $type,
        public string $value,
    ) {
        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException('Discount value must be a non-negative decimal string (e.g. "10" or "10.00").');
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
