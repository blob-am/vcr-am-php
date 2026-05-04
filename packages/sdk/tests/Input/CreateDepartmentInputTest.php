<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\CreateDepartmentInput;
use BlobSolutions\VcrAm\TaxRegime;

it('serializes a minimal department (no externalId)', function (): void {
    $input = new CreateDepartmentInput(taxRegime: TaxRegime::Vat);

    expect($input->jsonSerialize())->toBe(['taxRegime' => 'vat']);
});

it('serializes a department with an externalId', function (): void {
    $input = new CreateDepartmentInput(
        taxRegime: TaxRegime::TurnoverTax,
        externalId: 'pos-dept-1',
    );

    expect($input->jsonSerialize())->toBe([
        'taxRegime' => 'turnover_tax',
        'externalId' => 'pos-dept-1',
    ]);
});

it('rejects an empty externalId when provided', function (): void {
    new CreateDepartmentInput(
        taxRegime: TaxRegime::Vat,
        externalId: '   ',
    );
})->throws(InvalidArgumentException::class, 'externalId must not be empty');
