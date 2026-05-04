<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\RefundAmount;

it('serializes a cash-only refund', function (): void {
    expect((new RefundAmount(cash: '500'))->jsonSerialize())
        ->toBe(['cash' => '500']);
});

it('serializes a nonCash-only refund', function (): void {
    expect((new RefundAmount(nonCash: '500'))->jsonSerialize())
        ->toBe(['nonCash' => '500']);
});

it('serializes a refund split across cash and nonCash', function (): void {
    expect((new RefundAmount(cash: '300', nonCash: '200'))->jsonSerialize())
        ->toBe(['cash' => '300', 'nonCash' => '200']);
});

it('rejects when neither cash nor nonCash is set', function (): void {
    new RefundAmount();
})->throws(InvalidArgumentException::class, 'requires at least one of: cash, nonCash');

it('rejects an empty cash bucket', function (): void {
    new RefundAmount(cash: '   ');
})->throws(InvalidArgumentException::class, 'cash must be a non-negative decimal string');

it('rejects an empty nonCash bucket', function (): void {
    new RefundAmount(nonCash: '   ');
})->throws(InvalidArgumentException::class, 'nonCash must be a non-negative decimal string');

it('rejects a non-numeric cash value', function (): void {
    new RefundAmount(cash: '300x');
})->throws(InvalidArgumentException::class, 'cash must be a non-negative decimal string');
