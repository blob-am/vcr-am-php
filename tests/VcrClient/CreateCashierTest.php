<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\CreateCashierInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\Model\CreateCashierResponse;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeCreateCashierInput(): CreateCashierInput
{
    return new CreateCashierInput(
        name: new LocalizedName(
            value: ['hy' => 'Հաշվապահ-1'],
            localizationStrategy: LocalizationStrategy::Transliteration,
        ),
        password: '4242',
    );
}

it('parses a successful createCashier response', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['id' => 12, 'deskId' => 'desk-12'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->createCashier(makeCreateCashierInput());

    expect($response)->toBeInstanceOf(CreateCashierResponse::class)
        ->and($response->id)->toBe(12)
        ->and($response->deskId)->toBe('desk-12');
});

it('sends a POST request to /cashiers with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['id' => 1, 'deskId' => 'desk-1'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->createCashier(makeCreateCashierInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/cashiers')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'name' => [
            'value' => ['hy' => 'Հաշվապահ-1'],
            'localizationStrategy' => 'transliteration',
        ],
        'password' => '4242',
    ]);
});

it('surfaces server-side rejection as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'DESK_TAKEN',
        'message' => 'A cashier with that name already exists.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(409, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->createCashier(makeCreateCashierInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(409)
            ->and($e->apiErrorCode)->toBe('DESK_TAKEN');
    }
});
