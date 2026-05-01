<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\PrepaymentAmount;

it('serializes a cash-only prepayment', function (): void {
    expect((new PrepaymentAmount(cash: '500'))->jsonSerialize())
        ->toBe(['cash' => '500']);
});

it('serializes a nonCash-only prepayment', function (): void {
    expect((new PrepaymentAmount(nonCash: '500'))->jsonSerialize())
        ->toBe(['nonCash' => '500']);
});

it('serializes a prepayment split across cash and nonCash', function (): void {
    expect((new PrepaymentAmount(cash: '300', nonCash: '200'))->jsonSerialize())
        ->toBe(['cash' => '300', 'nonCash' => '200']);
});

it('rejects when neither cash nor nonCash is set', function (): void {
    new PrepaymentAmount();
})->throws(InvalidArgumentException::class, 'requires at least one of: cash, nonCash');

it('rejects an empty cash bucket', function (): void {
    new PrepaymentAmount(cash: '   ');
})->throws(InvalidArgumentException::class, 'cash must not be empty');

it('rejects an empty nonCash bucket', function (): void {
    new PrepaymentAmount(nonCash: '   ');
})->throws(InvalidArgumentException::class, 'nonCash must not be empty');
