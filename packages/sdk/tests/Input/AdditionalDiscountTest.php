<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\AdditionalDiscountType;
use BlobSolutions\VcrAm\Input\AdditionalDiscount;

it('serializes a percent additional discount', function (): void {
    expect((new AdditionalDiscount(AdditionalDiscountType::Percent, '5'))->jsonSerialize())
        ->toBe(['type' => 'percent', 'value' => '5']);
});

it('serializes a total additional discount', function (): void {
    expect((new AdditionalDiscount(AdditionalDiscountType::Total, '25'))->jsonSerialize())
        ->toBe(['type' => 'total', 'value' => '25']);
});

it('rejects an empty value', function (): void {
    new AdditionalDiscount(AdditionalDiscountType::Total, '   ');
})->throws(InvalidArgumentException::class, 'Discount value must be a non-negative decimal string');

it('rejects a non-numeric value', function (): void {
    new AdditionalDiscount(AdditionalDiscountType::Total, '5%');
})->throws(InvalidArgumentException::class, 'Discount value must be a non-negative decimal string');
