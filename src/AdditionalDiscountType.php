<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Discount type for the secondary "additional" discount, applied on top of
 * an existing {@see BaseDiscountType base} discount. The API rejects
 * {@see BaseDiscountType::Price} here — additional discounts cannot reduce
 * per-unit price.
 */
enum AdditionalDiscountType: string
{
    case Percent = 'percent';
    case Total = 'total';
}
