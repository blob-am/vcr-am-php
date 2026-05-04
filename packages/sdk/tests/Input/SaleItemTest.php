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
        department: new Department(1),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
    );
}

it('serializes a minimal item without optional fields', function (): void {
    expect(json_encode(makeMinimalSaleItem(), JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'offer' => ['externalId' => 'sku-1'],
            'department' => ['id' => 1],
            'quantity' => '1',
            'price' => '100',
            'unit' => 'pc',
        ], JSON_THROW_ON_ERROR));
});

it('serializes an item with discounts', function (): void {
    $item = new SaleItem(
        offer: Offer::existing('sku-1'),
        department: new Department(1),
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
            'department' => ['id' => 1],
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
        department: new Department(1),
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
        department: new Department(1),
        quantity: '   ',
        price: '100',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'quantity must be a non-negative decimal string');

it('rejects a non-numeric quantity', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        department: new Department(1),
        quantity: '1.5kg',
        price: '100',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'quantity must be a non-negative decimal string');

it('rejects an empty price', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        department: new Department(1),
        quantity: '1',
        price: '   ',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'price must be a non-negative decimal string');

it('rejects a negative price', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        department: new Department(1),
        quantity: '1',
        price: '-50',
        unit: Unit::Piece,
    );
})->throws(InvalidArgumentException::class, 'price must be a non-negative decimal string');

it('rejects an empty totalAmountTolerance string when provided', function (): void {
    new SaleItem(
        offer: Offer::existing('sku-1'),
        department: new Department(1),
        quantity: '1',
        price: '100',
        unit: Unit::Piece,
        totalAmountTolerance: '   ',
    );
})->throws(InvalidArgumentException::class, 'totalAmountTolerance must be a non-negative decimal string');
