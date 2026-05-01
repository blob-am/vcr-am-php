<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\SaleAmount;

it('serializes a single payment bucket', function (): void {
    $amount = new SaleAmount(cash: '1500');

    expect($amount->jsonSerialize())->toBe(['cash' => '1500']);
});

it('serializes multiple payment buckets in deterministic order', function (): void {
    $amount = new SaleAmount(prepayment: '500', nonCash: '500', cash: '500');

    expect($amount->jsonSerialize())->toBe([
        'prepayment' => '500',
        'nonCash' => '500',
        'cash' => '500',
    ]);
});

it('rejects when no buckets are set', function (): void {
    new SaleAmount();
})->throws(InvalidArgumentException::class, 'requires at least one of: prepayment, compensation, nonCash, cash.');

it('rejects an empty string in any bucket', function (): void {
    new SaleAmount(cash: '   ');
})->throws(InvalidArgumentException::class, 'cash must not be empty');
