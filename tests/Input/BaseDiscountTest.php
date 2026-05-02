<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\BaseDiscountType;
use BlobSolutions\VcrAm\Input\BaseDiscount;

it('serializes a percent discount', function (): void {
    expect((new BaseDiscount(BaseDiscountType::Percent, '10'))->jsonSerialize())
        ->toBe(['type' => 'percent', 'value' => '10']);
});

it('serializes a price discount', function (): void {
    expect((new BaseDiscount(BaseDiscountType::Price, '50'))->jsonSerialize())
        ->toBe(['type' => 'price', 'value' => '50']);
});

it('serializes a total discount', function (): void {
    expect((new BaseDiscount(BaseDiscountType::Total, '100'))->jsonSerialize())
        ->toBe(['type' => 'total', 'value' => '100']);
});

it('rejects an empty value', function (): void {
    new BaseDiscount(BaseDiscountType::Percent, '   ');
})->throws(InvalidArgumentException::class, 'Discount value must be a non-negative decimal string');

it('rejects a non-numeric value', function (): void {
    new BaseDiscount(BaseDiscountType::Percent, 'ten');
})->throws(InvalidArgumentException::class, 'Discount value must be a non-negative decimal string');
