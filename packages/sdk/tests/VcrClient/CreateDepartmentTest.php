<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\CreateDepartmentInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\Model\CreateDepartmentResponse;
use BlobSolutions\VcrAm\TaxRegime;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

$title = fn (): LocalizedName => new LocalizedName(
    value: ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
    localizationStrategy: LocalizationStrategy::Translation,
);

it('parses a successful createDepartment response', function () use ($title): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'message' => 'Department created.',
        'department' => 5,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->createDepartment(new CreateDepartmentInput(
        taxRegime: TaxRegime::Vat,
        title: $title(),
    ));

    expect($response)->toBeInstanceOf(CreateDepartmentResponse::class)
        ->and($response->message)->toBe('Department created.')
        ->and($response->department)->toBe(5);
});

it('sends a POST request to /departments with the JSON-encoded input', function () use ($title): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['message' => 'OK', 'department' => 1], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $input = new CreateDepartmentInput(
        taxRegime: TaxRegime::MicroEnterprise,
        title: $title(),
        externalId: 'erp-dept-7',
    );
    $client->createDepartment($input);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/departments');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'taxRegime' => 'micro_enterprise',
        'title' => [
            'value' => ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
            'localizationStrategy' => 'translation',
        ],
        'externalId' => 'erp-dept-7',
    ]);
});

it('surfaces server-side rejection as VcrApiException', function () use ($title): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'TAX_REGIME_INVALID',
        'message' => 'Account is not registered for VAT.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(422, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->createDepartment(new CreateDepartmentInput(
            taxRegime: TaxRegime::Vat,
            title: $title(),
        ));
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(422)
            ->and($e->apiErrorCode)->toBe('TAX_REGIME_INVALID');
    }
});
