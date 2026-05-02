<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\CreateOfferInput;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\Model\CreateOfferResponse;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeCreateOfferInput(): CreateOfferInput
{
    return new CreateOfferInput(
        type: OfferType::Product,
        classifierCode: '01.01.01',
        title: new LocalizedName(
            value: ['hy' => 'Հաց'],
            localizationStrategy: LocalizationStrategy::Translation,
        ),
        defaultMeasureUnit: Unit::Kilogram,
        defaultDepartment: new Department(5),
    );
}

it('parses a successful createOffer response', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['offerId' => 99001], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->createOffer(makeCreateOfferInput());

    expect($response)->toBeInstanceOf(CreateOfferResponse::class)
        ->and($response->offerId)->toBe(99001);
});

it('sends a POST request to /offers with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['offerId' => 1], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->createOffer(makeCreateOfferInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/offers')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'type' => 'product',
        'classifierCode' => '01.01.01',
        'title' => [
            'value' => ['hy' => 'Հաց'],
            'localizationStrategy' => 'translation',
        ],
        'defaultMeasureUnit' => 'kg',
        'defaultDepartment' => ['id' => 5],
    ]);
});

it('surfaces server-side rejection as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'CLASSIFIER_CODE_UNKNOWN',
        'message' => 'classifierCode 01.01.01 is not in the SRC taxonomy.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(422, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->createOffer(makeCreateOfferInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(422)
            ->and($e->apiErrorCode)->toBe('CLASSIFIER_CODE_UNKNOWN');
    }
});
