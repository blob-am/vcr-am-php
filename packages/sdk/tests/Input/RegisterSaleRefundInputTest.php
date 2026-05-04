<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RefundAmount;
use BlobSolutions\VcrAm\Input\RefundItemInput;
use BlobSolutions\VcrAm\Input\RegisterSaleRefundInput;
use BlobSolutions\VcrAm\Input\SendReceiptToBuyer;
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\RefundReason;

it('serializes a minimal full-sale refund', function (): void {
    $input = new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 4711,
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['deskId' => 'desk-1'],
            'saleId' => 4711,
        ], JSON_THROW_ON_ERROR));
});

it('serializes a full refund with all optional fields populated', function (): void {
    $input = new RegisterSaleRefundInput(
        cashier: CashierId::byInternalId(7),
        saleId: 4711,
        receipt: new SendReceiptToBuyer('jane@example.com', Language::English),
        reason: RefundReason::DefectiveGoods,
        reasonNote: 'box arrived damaged',
        refundAmounts: new RefundAmount(cash: '1500'),
        items: [
            new RefundItemInput(srcId: 1001, quantity: '1'),
            new RefundItemInput(srcId: 1002, quantity: '2', emarks: ['EM-1']),
        ],
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['id' => 7],
            'saleId' => 4711,
            'receipt' => ['email' => 'jane@example.com', 'language' => 'en'],
            'reason' => 'defective_goods',
            'reasonNote' => 'box arrived damaged',
            'refundAmounts' => ['cash' => '1500'],
            'items' => [
                ['srcId' => 1001, 'quantity' => '1'],
                ['srcId' => 1002, 'quantity' => '2', 'emarks' => ['EM-1']],
            ],
        ], JSON_THROW_ON_ERROR));
});

it('preserves saleId at the zero boundary', function (): void {
    $input = new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 0,
    );

    expect($input->saleId)->toBe(0);
});

it('rejects a negative saleId', function (): void {
    new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: -1,
    );
})->throws(InvalidArgumentException::class, 'saleId must be non-negative.');

it('rejects an empty reasonNote when provided', function (): void {
    new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 1,
        reasonNote: '   ',
    );
})->throws(InvalidArgumentException::class, 'reasonNote must not be empty');

it('rejects an empty items list (use null for a full refund)', function (): void {
    new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 1,
        items: [],
    );
})->throws(InvalidArgumentException::class, 'items must contain at least one entry');
