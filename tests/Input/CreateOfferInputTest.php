<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CreateOfferInput;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;

function makeLocalizedOfferTitle(): LocalizedName
{
    return new LocalizedName(
        value: ['hy' => 'Հաց', 'en' => 'Bread'],
        localizationStrategy: LocalizationStrategy::Translation,
    );
}

it('serializes a minimal product offer (no externalId)', function (): void {
    $input = new CreateOfferInput(
        type: OfferType::Product,
        classifierCode: '01.01.01',
        title: makeLocalizedOfferTitle(),
        defaultMeasureUnit: Unit::Kilogram,
        defaultDepartment: new Department(5),
    );

    expect(json_encode($input, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'type' => 'product',
            'classifierCode' => '01.01.01',
            'title' => [
                'value' => ['hy' => 'Հաց', 'en' => 'Bread'],
                'localizationStrategy' => 'translation',
            ],
            'defaultMeasureUnit' => 'kg',
            'defaultDepartment' => ['id' => 5],
        ], JSON_THROW_ON_ERROR));
});

it('serializes a service offer with externalId', function (): void {
    $input = new CreateOfferInput(
        type: OfferType::Service,
        classifierCode: '96.09.19',
        title: makeLocalizedOfferTitle(),
        defaultMeasureUnit: Unit::Hour,
        defaultDepartment: new Department(2),
        externalId: 'erp-svc-7',
    );

    $serialized = json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR);

    expect($serialized)->toMatchArray([
        'type' => 'service',
        'classifierCode' => '96.09.19',
        'defaultMeasureUnit' => 'hr',
        'externalId' => 'erp-svc-7',
    ]);
});

it('rejects an empty classifierCode', function (): void {
    new CreateOfferInput(
        type: OfferType::Product,
        classifierCode: '   ',
        title: makeLocalizedOfferTitle(),
        defaultMeasureUnit: Unit::Piece,
        defaultDepartment: new Department(1),
    );
})->throws(InvalidArgumentException::class, 'classifierCode must not be empty.');

it('rejects an empty externalId when provided', function (): void {
    new CreateOfferInput(
        type: OfferType::Product,
        classifierCode: '01.01.01',
        title: makeLocalizedOfferTitle(),
        defaultMeasureUnit: Unit::Piece,
        defaultDepartment: new Department(1),
        externalId: '   ',
    );
})->throws(InvalidArgumentException::class, 'externalId must not be empty');
