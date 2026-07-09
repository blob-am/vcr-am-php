<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\AutoSettleTender;
use BlobSolutions\VcrAm\Input\AutoSettle;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;

/**
 * @return list<SaleItem>
 */
function oneItem(): array
{
    return [
        new SaleItem(
            offer: Offer::existing('sku-bread'),
            department: new Department(5),
            quantity: '2',
            price: '750',
            unit: Unit::Piece,
        ),
    ];
}

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

it('rejects a request that carries neither amount nor autoSettle', function (): void {
    new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: oneItem(),
        amount: null,
        buyer: Buyer::individual(),
    );
})->throws(InvalidArgumentException::class, 'exactly one of');

it('rejects a request that carries both amount and autoSettle', function (): void {
    new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: oneItem(),
        amount: new SaleAmount(cash: '1500'),
        buyer: Buyer::individual(),
        autoSettle: new AutoSettle(AutoSettleTender::Cash),
    );
})->throws(InvalidArgumentException::class, 'exactly one of');

it('json_encodes an autoSettle sale to the wire format (no amount key)', function (): void {
    $input = RegisterSaleInput::withAutoSettle(
        cashier: CashierId::byDeskId('desk-1'),
        items: oneItem(),
        autoSettle: new AutoSettle(AutoSettleTender::NonCash),
        buyer: Buyer::individual(),
    );

    $decoded = json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toBe([
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
        'buyer' => ['type' => 'individual'],
        'autoSettle' => ['tender' => 'nonCash'],
    ]);
});

it('builds an amount sale via the withAmount named constructor', function (): void {
    $input = RegisterSaleInput::withAmount(
        cashier: CashierId::byDeskId('desk-1'),
        items: oneItem(),
        amount: new SaleAmount(cash: '1500'),
        buyer: Buyer::individual(),
    );

    expect($input->amount)->not->toBeNull()
        ->and($input->autoSettle)->toBeNull();
});
