<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;

it('rejects an empty items list', function (): void {
    new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: [],
        amount: new SaleAmount(cash: '100'),
        buyer: Buyer::individual(),
    );
})->throws(InvalidArgumentException::class, 'A sale must contain at least one item.');

it('json_encodes a minimal valid sale to the wire format', function (): void {
    $input = new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: [
            new SaleItem(
                offer: Offer::existing('sku-bread'),
                department: new Department(5),
                quantity: '2',
                price: '750',
                unit: Unit::Piece,
            ),
        ],
        amount: new SaleAmount(cash: '1500'),
        buyer: Buyer::individual(),
    );

    $json = json_encode($input, JSON_THROW_ON_ERROR);

    expect($json)->toBe(json_encode([
        'cashier' => ['deskId' => 'desk-1'],
        'items' => [
            [
                'offer' => ['externalId' => 'sku-bread'],
                'department' => ['id' => 5],
                'quantity' => '2',
                'price' => '750',
                'unit' => 'pc',
            ],
        ],
        'amount' => ['cash' => '1500'],
        'buyer' => ['type' => 'individual'],
    ], JSON_THROW_ON_ERROR));
});
