<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Discount type for the primary discount applied to a sale item.
 *
 * @see AdditionalDiscountType for the secondary "additional" discount, which
 *      omits {@see self::Price} since the API does not allow per-unit price
 *      reductions on top of an existing discount.
 */
enum BaseDiscountType: string
{
    case Percent = 'percent';
    case Price = 'price';
    case Total = 'total';
}
