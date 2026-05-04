<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput;
use BlobSolutions\VcrAm\Input\SendReceiptToBuyer;
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\RefundReason;

it('serializes a minimal full prepayment refund', function (): void {
    $input = new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: 9001,
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['deskId' => 'desk-1'],
            'prepaymentId' => 9001,
        ], JSON_THROW_ON_ERROR));
});

it('serializes a prepayment refund with all optional fields populated', function (): void {
    $input = new RegisterPrepaymentRefundInput(
        cashier: CashierId::byInternalId(3),
        prepaymentId: 9001,
        receipt: new SendReceiptToBuyer('jane@example.com', Language::Russian),
        reason: RefundReason::CustomerRequest,
        reasonNote: 'changed their mind',
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'cashier' => ['id' => 3],
            'prepaymentId' => 9001,
            'receipt' => ['email' => 'jane@example.com', 'language' => 'ru'],
            'reason' => 'customer_request',
            'reasonNote' => 'changed their mind',
        ], JSON_THROW_ON_ERROR));
});

it('preserves prepaymentId at the zero boundary', function (): void {
    $input = new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: 0,
    );

    expect($input->prepaymentId)->toBe(0);
});

it('rejects a negative prepaymentId', function (): void {
    new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: -1,
    );
})->throws(InvalidArgumentException::class, 'prepaymentId must be non-negative.');

it('rejects an empty reasonNote when provided', function (): void {
    new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: 1,
        reasonNote: '   ',
    );
})->throws(InvalidArgumentException::class, 'reasonNote must not be empty');
