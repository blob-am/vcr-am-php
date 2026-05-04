<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CashierId;

it('serializes byInternalId as {id: int}', function (): void {
    $cashier = CashierId::byInternalId(42);

    expect($cashier->jsonSerialize())->toBe(['id' => 42]);
});

it('serializes byDeskId as {deskId: string}', function (): void {
    $cashier = CashierId::byDeskId('desk-1');

    expect($cashier->jsonSerialize())->toBe(['deskId' => 'desk-1']);
});

it('rejects a negative internal id', function (): void {
    CashierId::byInternalId(-1);
})->throws(InvalidArgumentException::class, 'Cashier id must be non-negative.');

it('rejects an empty deskId', function (): void {
    CashierId::byDeskId('   ');
})->throws(InvalidArgumentException::class, 'Cashier deskId must not be empty.');
