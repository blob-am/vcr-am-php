<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Language;
use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('returns an empty list when the server returns no cashiers', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $cashiers = $client->listCashiers();

    expect($cashiers)->toBe([]);
});

it('parses a list of cashiers with localised names', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        [
            'deskId' => 'desk-1',
            'internalId' => 1,
            'name' => [
                'hy' => ['language' => 'hy', 'content' => 'Հաշվապահ'],
                'en' => ['language' => 'en', 'content' => 'Cashier'],
            ],
        ],
        [
            'deskId' => 'desk-2',
            'internalId' => 7,
            'name' => [
                'multi' => ['language' => 'multi', 'content' => 'Universal'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $cashiers = $client->listCashiers();

    Assert::assertCount(2, $cashiers);
    [$first, $second] = $cashiers;

    expect($first->deskId)->toBe('desk-1')
        ->and($first->internalId)->toBe(1);

    Assert::assertArrayHasKey('hy', $first->name);
    Assert::assertArrayHasKey('multi', $second->name);

    expect($first->name['hy']->language)->toBe(Language::Armenian)
        ->and($first->name['hy']->content)->toBe('Հաշվապահ')
        ->and($second->name['multi']->language)->toBe(Language::Multi);
});

it('sends a GET request to /cashiers with the X-API-Key header', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listCashiers();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/cashiers')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('User-Agent'))->toStartWith('vcr-am-sdk-php/');
});

it('does not attach a request body to a GET', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listCashiers();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect((string) $request->getBody())->toBe('')
        ->and($request->hasHeader('Content-Type'))->toBeFalse();
});

it('throws VcrApiException on HTTP 401 with parsed error envelope', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['code' => 'INVALID_TOKEN', 'message' => 'API key revoked'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(401, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(401)
            ->and($e->apiErrorCode)->toBe('INVALID_TOKEN')
            ->and($e->apiErrorMessage)->toBe('API key revoked');
    }
});

it('throws VcrApiException on HTTP 500 even when the body is HTML, with null error fields', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(500, ['Content-Type' => 'text/html'], '<html><body>500</body></html>'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->apiErrorCode)->toBeNull()
            ->and($e->apiErrorMessage)->toBeNull()
            ->and($e->rawBody)->toBe('<html><body>500</body></html>');
    }
});

it('throws VcrNetworkException when the PSR-18 client throws', function (): void {
    [$client, $mock] = makeMockedClient();
    $factory = new Psr17Factory();
    $cause = new NetworkException('Connection refused', $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'));
    $mock->addException($cause);

    $client->listCashiers();
})->throws(VcrNetworkException::class);

it('throws VcrValidationException when the response is not valid JSON', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{not json'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrValidationException');
    } catch (VcrValidationException $e) {
        expect($e->detail)->toContain('not valid JSON');
    }
});

it('throws VcrValidationException when the response shape does not match', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        ['deskId' => 'desk-1', 'internalId' => 'should-be-int', 'name' => []],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->listCashiers();
})->throws(VcrValidationException::class);

it('throws VcrValidationException when the JSON root is a scalar', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '"unexpected"'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrValidationException');
    } catch (VcrValidationException $e) {
        expect($e->detail)->toContain('expected JSON array or object');
    }
});

it('handles a 4xx response whose JSON body is a non-object root (extractApiError null path)', function (): void {
    [$client, $mock] = makeMockedClient();
    // Valid JSON but a scalar root — extractApiError can't pluck code/message.
    $mock->addResponse(new Response(500, ['Content-Type' => 'application/json'], '"server exploded"'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->apiErrorCode)->toBeNull()
            ->and($e->apiErrorMessage)->toBeNull()
            ->and($e->rawBody)->toBe('"server exploded"');
    }
});

it('handles a 4xx response whose error envelope has non-string code and message', function (): void {
    [$client, $mock] = makeMockedClient();
    // Server bug: emits code/message as non-strings. Defensive narrowing kicks in.
    $body = json_encode(['code' => 42, 'message' => null], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->apiErrorCode)->toBeNull()
            ->and($e->apiErrorMessage)->toBeNull();
    }
});

it('strips the X-API-Key header from the request on VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(500, [], 'oops'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->request->hasHeader('X-API-Key'))->toBeFalse();
    }
});

it('strips the X-API-Key header from the request on VcrNetworkException', function (): void {
    [$client, $mock] = makeMockedClient();
    $factory = new Psr17Factory();
    $cause = new NetworkException('boom', $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'));
    $mock->addException($cause);

    try {
        $client->listCashiers();
        Assert::fail('expected VcrNetworkException');
    } catch (VcrNetworkException $e) {
        expect($e->request->hasHeader('X-API-Key'))->toBeFalse();
    }
});

it('strips the X-API-Key header from the request on VcrValidationException', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '{not json'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrValidationException');
    } catch (VcrValidationException $e) {
        expect($e->request->hasHeader('X-API-Key'))->toBeFalse();
    }
});
