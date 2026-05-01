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

it('serializes a prepayment-only amount', function (): void {
    expect((new SaleAmount(prepayment: '500'))->jsonSerialize())
        ->toBe(['prepayment' => '500']);
});

it('serializes a compensation-only amount', function (): void {
    expect((new SaleAmount(compensation: '300'))->jsonSerialize())
        ->toBe(['compensation' => '300']);
});

it('serializes a nonCash-only amount', function (): void {
    expect((new SaleAmount(nonCash: '700'))->jsonSerialize())
        ->toBe(['nonCash' => '700']);
});

it('rejects an empty cash bucket', function (): void {
    new SaleAmount(cash: '   ');
})->throws(InvalidArgumentException::class, 'cash must not be empty');

it('rejects an empty prepayment bucket', function (): void {
    new SaleAmount(prepayment: '   ');
})->throws(InvalidArgumentException::class, 'prepayment must not be empty');

it('rejects an empty compensation bucket', function (): void {
    new SaleAmount(compensation: '   ');
})->throws(InvalidArgumentException::class, 'compensation must not be empty');

it('rejects an empty nonCash bucket', function (): void {
    new SaleAmount(nonCash: '   ');
})->throws(InvalidArgumentException::class, 'nonCash must not be empty');
