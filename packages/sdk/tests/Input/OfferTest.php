<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\OfferTitle;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\OfferTitleType;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;

it('serializes an existing offer as just externalId', function (): void {
    $offer = Offer::existing('sku-123');

    expect($offer->isNew())->toBeFalse()
        ->and($offer->jsonSerialize())->toBe(['externalId' => 'sku-123']);
});

it('serializes a new offer with all fields', function (): void {
    $title = OfferTitle::universal('Bread');
    $offer = Offer::createNew(
        externalId: 'sku-bread',
        title: $title,
        type: OfferType::Product,
        classifierCode: '01.01.01',
        defaultMeasureUnit: Unit::Kilogram,
        defaultDepartment: new Department(5),
    );

    expect($offer->isNew())->toBeTrue();

    $serialized = $offer->jsonSerialize();
    expect($serialized)->toMatchArray([
        'type' => 'product',
        'classifierCode' => '01.01.01',
        'defaultMeasureUnit' => 'kg',
        'externalId' => 'sku-bread',
    ]);
    expect($serialized)->toHaveKeys(['title', 'defaultDepartment']);
});

it('rejects an empty externalId on existing offer', function (): void {
    Offer::existing('  ');
})->throws(InvalidArgumentException::class, 'externalId must not be empty.');

it('rejects an empty externalId on createNew offer', function (): void {
    Offer::createNew(
        externalId: '   ',
        title: OfferTitle::universal('Bread'),
        type: OfferType::Product,
        classifierCode: '01.01.01',
        defaultMeasureUnit: Unit::Kilogram,
        defaultDepartment: new Department(1),
    );
})->throws(InvalidArgumentException::class, 'externalId must not be empty.');

it('rejects an empty classifierCode on createNew offer', function (): void {
    Offer::createNew(
        externalId: 'sku-1',
        title: OfferTitle::universal('Bread'),
        type: OfferType::Product,
        classifierCode: '   ',
        defaultMeasureUnit: Unit::Kilogram,
        defaultDepartment: new Department(1),
    );
})->throws(InvalidArgumentException::class, 'classifierCode must not be empty.');

it('serializes a universal offer title', function (): void {
    $title = OfferTitle::universal('Bread');

    expect($title->type)->toBe(OfferTitleType::Universal)
        ->and($title->jsonSerialize())->toBe([
            'type' => 'universal',
            'content' => 'Bread',
        ]);
});

it('serializes a localized offer title with strategy', function (): void {
    $title = OfferTitle::localized(
        ['hy' => 'Հաց', 'ru' => 'Хлеб'],
        LocalizationStrategy::Translation,
    );

    expect($title->jsonSerialize())->toBe([
        'type' => 'localized',
        'content' => ['hy' => 'Հաց', 'ru' => 'Хлеб'],
        'localizationStrategy' => 'translation',
    ]);
});

it('rejects a localized offer title without the hy key', function (): void {
    OfferTitle::localized(['ru' => 'Хлеб'], LocalizationStrategy::Translation);
})->throws(InvalidArgumentException::class, 'requires the "hy" key');

it('rejects a localized offer title with empty hy content', function (): void {
    OfferTitle::localized(['hy' => '   '], LocalizationStrategy::Transliteration);
})->throws(InvalidArgumentException::class, '"hy" content must not be empty');

it('rejects a universal offer title with empty content', function (): void {
    OfferTitle::universal('   ');
})->throws(InvalidArgumentException::class, 'Universal offer title content must not be empty.');
