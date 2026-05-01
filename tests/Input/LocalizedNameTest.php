<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;

it('serializes the value map and strategy', function (): void {
    $name = new LocalizedName(
        value: ['hy' => 'Հաշվապահ', 'en' => 'Cashier'],
        localizationStrategy: LocalizationStrategy::Translation,
    );

    expect($name->jsonSerialize())->toBe([
        'value' => ['hy' => 'Հաշվապահ', 'en' => 'Cashier'],
        'localizationStrategy' => 'translation',
    ]);
});

it('rejects a value missing the hy key', function (): void {
    new LocalizedName(
        value: ['en' => 'Cashier'],
        localizationStrategy: LocalizationStrategy::Translation,
    );
})->throws(InvalidArgumentException::class, 'requires the "hy" key');

it('rejects an empty hy content', function (): void {
    new LocalizedName(
        value: ['hy' => '   '],
        localizationStrategy: LocalizationStrategy::Transliteration,
    );
})->throws(InvalidArgumentException::class, '"hy" content must not be empty');
