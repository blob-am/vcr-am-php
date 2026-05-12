<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CreateDepartmentInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\TaxRegime;

$title = fn (): LocalizedName => new LocalizedName(
    value: ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
    localizationStrategy: LocalizationStrategy::Translation,
);

it('serializes a minimal department (no externalId)', function () use ($title): void {
    $input = new CreateDepartmentInput(
        taxRegime: TaxRegime::Vat,
        title: $title(),
    );

    expect(json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'taxRegime' => 'vat',
            'title' => [
                'value' => ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
                'localizationStrategy' => 'translation',
            ],
        ]);
});

it('serializes a department with an externalId', function () use ($title): void {
    $input = new CreateDepartmentInput(
        taxRegime: TaxRegime::TurnoverTax,
        title: $title(),
        externalId: 'pos-dept-1',
    );

    expect(json_decode(json_encode($input, JSON_THROW_ON_ERROR), associative: true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            'taxRegime' => 'turnover_tax',
            'title' => [
                'value' => ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
                'localizationStrategy' => 'translation',
            ],
            'externalId' => 'pos-dept-1',
        ]);
});

it('rejects an empty externalId when provided', function () use ($title): void {
    new CreateDepartmentInput(
        taxRegime: TaxRegime::Vat,
        title: $title(),
        externalId: '   ',
    );
})->throws(InvalidArgumentException::class, 'externalId must not be empty');

it('requires a non-empty Armenian title', function (): void {
    new CreateDepartmentInput(
        taxRegime: TaxRegime::Vat,
        title: new LocalizedName(
            value: ['ru' => 'Продукты'],
            localizationStrategy: LocalizationStrategy::Translation,
        ),
    );
})->throws(InvalidArgumentException::class, '"hy" key');
