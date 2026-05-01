<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\PrepaymentAmount;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentInput;

it('serializes a minimal prepayment to the wire format', function (): void {
    $input = new RegisterPrepaymentInput(
        cashier: CashierId::byDeskId('desk-1'),
        amount: new PrepaymentAmount(cash: '5000'),
        buyer: Buyer::individual(),
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['deskId' => 'desk-1'],
            'amount' => ['cash' => '5000'],
            'buyer' => ['type' => 'individual'],
        ], JSON_THROW_ON_ERROR));
});

it('serializes a business-entity prepayment', function (): void {
    $input = new RegisterPrepaymentInput(
        cashier: CashierId::byInternalId(7),
        amount: new PrepaymentAmount(nonCash: '10000'),
        buyer: Buyer::businessEntity('12345678'),
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['id' => 7],
            'amount' => ['nonCash' => '10000'],
            'buyer' => ['type' => 'business_entity', 'tin' => '12345678'],
        ], JSON_THROW_ON_ERROR));
});
