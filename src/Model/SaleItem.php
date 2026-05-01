<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\AdditionalDiscountType;
use BlobSolutions\VcrAm\BaseDiscountType;
use BlobSolutions\VcrAm\Unit;

/**
 * One line item as embedded in a sale detail response — distinct from
 * {@see \BlobSolutions\VcrAm\Input\SaleItem} (which models the request shape).
 *
 * Numeric fields (`quantity`, `price`, `discount`, `additionalDiscount`)
 * arrive as JSON numbers because the API's Prisma `Float` columns serialise
 * to numbers, not strings.
 */
final readonly class SaleItem
{
    public function __construct(
        public int $srcId,
        public float $quantity,
        public float $price,
        public Unit $unit,
        public ?float $discount,
        public ?BaseDiscountType $discountType,
        public ?float $additionalDiscount,
        public ?AdditionalDiscountType $additionalDiscountType,
        public SaleDepartment $department,
        public SaleOffer $offer,
    ) {
    }
}
