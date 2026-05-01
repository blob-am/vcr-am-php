<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\AdditionalDiscountType;
use BlobSolutions\VcrAm\BaseDiscountType;
use BlobSolutions\VcrAm\Input\AdditionalDiscount;
use BlobSolutions\VcrAm\Input\BaseDiscount;
use BlobSolutions\VcrAm\Input\Discounts;

it('serializes a base-only Discounts', function (): void {
    $discounts = new Discounts(base: new BaseDiscount(BaseDiscountType::Percent, '10'));

    expect(json_encode($discounts, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'base' => ['type' => 'percent', 'value' => '10'],
        ], JSON_THROW_ON_ERROR));
});

it('serializes an additional-only Discounts', function (): void {
    $discounts = new Discounts(additional: new AdditionalDiscount(AdditionalDiscountType::Total, '5'));

    expect(json_encode($discounts, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'additional' => ['type' => 'total', 'value' => '5'],
        ], JSON_THROW_ON_ERROR));
});

it('serializes both base and additional', function (): void {
    $discounts = new Discounts(
        base: new BaseDiscount(BaseDiscountType::Price, '50'),
        additional: new AdditionalDiscount(AdditionalDiscountType::Percent, '5'),
    );

    expect(json_encode($discounts, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'base' => ['type' => 'price', 'value' => '50'],
            'additional' => ['type' => 'percent', 'value' => '5'],
        ], JSON_THROW_ON_ERROR));
});

it('rejects when neither base nor additional is set', function (): void {
    new Discounts();
})->throws(InvalidArgumentException::class, 'requires at least one of: base, additional');
