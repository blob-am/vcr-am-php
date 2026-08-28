<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\AdditionalDiscountType;
use BlobSolutions\VcrAm\BaseDiscountType;
use BlobSolutions\VcrAm\Input\AdditionalDiscount;
use BlobSolutions\VcrAm\Input\BaseDiscount;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Discounts;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;

function makeMinimalSaleItem(): SaleItem
{
    return new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
    );
}

it('serializes a minimal item without optional fields', function (): void {
    expect(json_encode(makeMinimalSaleItem(), JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'quantity' => '1',
            'price' => '100',
            'unit' => 'pc',
        ], JSON_THROW_ON_ERROR));
});

it('omits the department entirely so the server inherits the offer\'s', function (): void {
    // Absent, not null: the server distinguishes "inherit" from an explicit
    // value, and a null fails its schema.
    expect(json_encode(makeMinimalSaleItem(), JSON_THROW_ON_ERROR))->not->toContain('department');
});

it('serializes an explicit department that overrides the offer\'s', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        department: new Department(2),
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'quantity' => '1',
            'price' => '100',
            'unit' => 'pc',
            'department' => ['id' => 2],
        ], JSON_THROW_ON_ERROR));
});

it('serializes an item with discounts', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '2',
        price: '500',
        unit: Unit::Kilogram,
        discounts: new Discounts(
            base: new BaseDiscount(BaseDiscountType::Percent, '10'),
            additional: new AdditionalDiscount(AdditionalDiscountType::Total, '5'),
        ),
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'quantity' => '2',
            'price' => '500',
            'unit' => 'kg',
            'discounts' => [
                'base' => ['type' => 'percent', 'value' => '10'],
                'additional' => ['type' => 'total', 'value' => '5'],
            ],
        ], JSON_THROW_ON_ERROR));
});

it('serializes an item with totalAmountTolerance', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        totalAmountTolerance: '0.01',
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))
        ->toContain('"totalAmountTolerance":"0.01"');
});

it('rejects an empty quantity', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '   ',
        price: '100',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'quantity must be a non-negative decimal string');

it('rejects a non-numeric quantity', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1.5kg',
        price: '100',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'quantity must be a non-negative decimal string');

it('rejects an empty price', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '   ',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'price must be a non-negative decimal string');

it('rejects a negative price', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '-50',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'price must be a non-negative decimal string');

it('rejects an empty totalAmountTolerance string when provided', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        totalAmountTolerance: '   ',
    );
})->throws(InvalidArgumentException::class, 'totalAmountTolerance must be a non-negative decimal string');

it('serializes an item with emarks', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '2',
        price: '7500',
        unit: Unit::Bottle,
        emarks: ['010460700818472821CIQ%a^4Q', '010460700818472821CXR<7%qK'],
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'quantity' => '2',
            'price' => '7500',
            'unit' => 'bottle',
            'emarks' => ['010460700818472821CIQ%a^4Q', '010460700818472821CXR<7%qK'],
        ], JSON_THROW_ON_ERROR));
});

it('omits the emarks field when null', function (): void {
    $item = makeMinimalSaleItem();

    expect(json_encode($item, JSON_THROW_ON_ERROR))->not->toContain('emarks');
});

it('omits the emarks field when explicitly null', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        emarks: null,
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))->not->toContain('emarks');
});

it('serializes an empty emarks array as []', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        emarks: [],
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))->toContain('"emarks":[]');
});

it('rejects an empty emark entry', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        emarks: ['VALID-CODE', '   '],
    );
})->throws(InvalidArgumentException::class, 'emarks entries must not be empty.');

it('serializes an item with a foreign currency (price stays as typed)', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '10',
        unit: Unit::Piece,
        currency: 'USD',
    );

    expect(json_encode($item, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'quantity' => '1',
            'price' => '10',
            'unit' => 'pc',
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR));
});

it('omits the currency field when null (native AMD line)', function (): void {
    expect(json_encode(makeMinimalSaleItem(), JSON_THROW_ON_ERROR))->not->toContain('currency');
});

it('rejects a currency that is not a 3-letter code', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        currency: 'US',
    );
})->throws(InvalidArgumentException::class, 'currency must be a 3-letter ISO 4217 code');
