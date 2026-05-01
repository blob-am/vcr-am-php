<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CreateCashierInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;

function makeLocalizedCashierName(): LocalizedName
{
    return new LocalizedName(
        value: ['hy' => 'Հաշվապահ', 'en' => 'Cashier'],
        localizationStrategy: LocalizationStrategy::Translation,
    );
}

it('serializes name + password to the wire shape', function (): void {
    $input = new CreateCashierInput(
        name: makeLocalizedCashierName(),
        password: '1234',
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'name' => [
                'value' => ['hy' => 'Հաշվապահ', 'en' => 'Cashier'],
                'localizationStrategy' => 'translation',
            ],
            'password' => '1234',
        ], JSON_THROW_ON_ERROR));
});

it('accepts the minimum password length (4 digits)', function (): void {
    expect((new CreateCashierInput(makeLocalizedCashierName(), '1234'))->password)->toBe('1234');
});

it('accepts the maximum password length (8 digits)', function (): void {
    expect((new CreateCashierInput(makeLocalizedCashierName(), '12345678'))->password)->toBe('12345678');
});

it('rejects a password shorter than 4 digits', function (): void {
    new CreateCashierInput(makeLocalizedCashierName(), '123');
})->throws(InvalidArgumentException::class, '4-8 digit numeric PIN');

it('rejects a password longer than 8 digits', function (): void {
    new CreateCashierInput(makeLocalizedCashierName(), '123456789');
})->throws(InvalidArgumentException::class, '4-8 digit numeric PIN');

it('rejects a password with non-digit characters', function (): void {
    new CreateCashierInput(makeLocalizedCashierName(), '12ab');
})->throws(InvalidArgumentException::class, '4-8 digit numeric PIN');

it('rejects an empty password', function (): void {
    new CreateCashierInput(makeLocalizedCashierName(), '');
})->throws(InvalidArgumentException::class, '4-8 digit numeric PIN');
