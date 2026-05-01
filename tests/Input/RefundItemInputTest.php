<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\RefundItemInput;

it('serializes a refund item without emarks', function (): void {
    $item = new RefundItemInput(srcId: 1001, quantity: '2');

    expect($item->jsonSerialize())->toBe([
        'srcId' => 1001,
        'quantity' => '2',
    ]);
});

it('serializes a refund item with emarks', function (): void {
    $item = new RefundItemInput(
        srcId: 1001,
        quantity: '1',
        emarks: ['EMARK-001', 'EMARK-002'],
    );

    expect($item->jsonSerialize())->toBe([
        'srcId' => 1001,
        'quantity' => '1',
        'emarks' => ['EMARK-001', 'EMARK-002'],
    ]);
});

it('preserves srcId boundary at zero', function (): void {
    expect((new RefundItemInput(0, '1'))->srcId)->toBe(0);
});

it('rejects a negative srcId', function (): void {
    new RefundItemInput(srcId: -1, quantity: '1');
})->throws(InvalidArgumentException::class, 'srcId must be non-negative.');

it('rejects an empty quantity', function (): void {
    new RefundItemInput(srcId: 1, quantity: '   ');
})->throws(InvalidArgumentException::class, 'quantity must not be empty.');

it('rejects an empty emark entry', function (): void {
    new RefundItemInput(srcId: 1, quantity: '1', emarks: ['EMARK-001', '   ']);
})->throws(InvalidArgumentException::class, 'emarks entries must not be empty.');
