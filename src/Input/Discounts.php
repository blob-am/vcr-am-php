<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Optional discounts applied to a sale item. At least one of `base` or
 * `additional` must be present — otherwise the field should be omitted
 * entirely from the parent {@see SaleItem}.
 */
final readonly class Discounts implements JsonSerializable
{
    public function __construct(
        public ?BaseDiscount $base = null,
        public ?AdditionalDiscount $additional = null,
    ) {
        if ($base === null && $additional === null) {
            throw new InvalidArgumentException(
                'Discounts requires at least one of: base, additional. Omit the discounts field on SaleItem instead of passing an empty Discounts.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [];

        if ($this->base !== null) {
            $payload['base'] = $this->base;
        }

        if ($this->additional !== null) {
            $payload['additional'] = $this->additional;
        }

        return $payload;
    }
}
