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
    $input = new CreateCashierInput(makeLocalizedCashierName(), '1234');

    expect(json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray(['password' => '1234']);
});

it('accepts the maximum password length (8 digits)', function (): void {
    $input = new CreateCashierInput(makeLocalizedCashierName(), '12345678');

    expect(json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray(['password' => '12345678']);
});

it('redacts the password from var_dump output', function (): void {
    $input = new CreateCashierInput(makeLocalizedCashierName(), '4242');

    ob_start();
    var_dump($input);
    $dump = (string) ob_get_clean();

    expect($dump)->toContain('[REDACTED]');
    expect(str_contains($dump, '4242'))->toBeFalse();
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
