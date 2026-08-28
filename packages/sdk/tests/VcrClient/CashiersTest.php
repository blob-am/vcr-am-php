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
    $body = json_encode(['error' => 'API key revoked'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(401, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(401)
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

it('handles a 4xx response whose JSON body is a non-object root', function (): void {
    [$client, $mock] = makeMockedClient();
    // Valid JSON but a scalar root — there is no envelope to read.
    $mock->addResponse(new Response(500, ['Content-Type' => 'application/json'], '"server exploded"'));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(500)
            ->and($e->apiErrorMessage)->toBeNull()
            ->and($e->rawBody)->toBe('"server exploded"');
    }
});

it('ignores a non-string error field rather than coercing it', function (): void {
    [$client, $mock] = makeMockedClient();
    // Server bug: emits `error` as a non-string. Defensive narrowing kicks in,
    // and rawBody still carries the whole thing for diagnosis.
    $body = json_encode(['error' => 42], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->apiErrorMessage)->toBeNull()
            ->and($e->rawBody)->toBe($body);
    }
});

// The bug these replace: the SDK read `code`/`message`, which the API has
// never sent — every error arrived as [null, null], and $e->apiErrorMessage
// was null on every single failure in production. The old tests passed
// because they fed the SDK the same invented envelope the SDK expected.
it('reads the error text the API actually sends', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode(['error' => 'X-API-Key header is required'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(401, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->apiErrorMessage)->toBe('X-API-Key header is required')
            ->and($e->getMessage())
            ->toBe('VCR.AM API returned HTTP 401: X-API-Key header is required');
    }
});

it('surfaces field-level validation issues', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Validation failed',
        'issues' => [
            ['path' => ['items', 0, 'price'], 'message' => 'Required', 'code' => 'invalid_type'],
            ['path' => [], 'message' => 'Root problem', 'code' => 'custom'],
        ],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        $first = $e->issues[0] ?? null;
        $second = $e->issues[1] ?? null;

        expect($e->issues)->toHaveCount(2)
            ->and($first?->pointer())->toBe('items.0.price')
            ->and($first?->message)->toBe('Required')
            ->and($first?->code)->toBe('invalid_type')
            // An empty path means the complaint is about the body as a whole.
            ->and($second?->pointer())->toBe('');
    }
});

it('drops malformed issue entries without losing the well-formed ones', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Validation failed',
        'issues' => [
            'not an object',
            ['message' => 'no code here'],
            ['path' => ['a'], 'message' => 'ok', 'code' => 'custom'],
        ],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        $only = $e->issues[0] ?? null;

        expect($e->issues)->toHaveCount(1)
            ->and($only?->message)->toBe('ok');
    }
});

it('surfaces the requestId so support can find the matching log entry', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Internal server error',
        'requestId' => '3f0c1c8e-1f2a-4c9e-9b1d-2b7a4c8e5f10',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(500, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->requestId)->toBe('3f0c1c8e-1f2a-4c9e-9b1d-2b7a4c8e5f10');
    }
});

// The costliest field in the envelope: without it a 502 reads as "nothing
// happened", the integrator retries, and the retry fiscalizes a second
// receipt — a real tax document that can only be refunded, never deleted.
it('surfaces the pending handle from a 502 so the caller does not resend', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Tax service (SRC) is temporarily unavailable.',
        'pending' => ['type' => 'sale', 'id' => 5122, 'statusUrl' => '/api/v1/sales/5122'],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(502, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->pending?->type)->toBe('sale')
            ->and($e->pending?->id)->toBe(5122)
            ->and($e->pending?->statusUrl)->toBe('/api/v1/sales/5122');
    }
});

it('accepts a pending type it does not know, rather than dropping the handle', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Tax service (SRC) is temporarily unavailable.',
        'pending' => ['type' => 'invoice', 'id' => 9, 'statusUrl' => '/api/v1/invoices/9'],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(502, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->pending?->type)->toBe('invoice');
    }
});

// Half a handle points at a document that may not be the one we persisted,
// and the entire point of the field is telling the caller not to resend.
it('rejects a partial pending handle outright', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'error' => 'Tax service (SRC) is temporarily unavailable.',
        'pending' => ['type' => 'sale', 'statusUrl' => '/api/v1/sales/5122'],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(502, ['Content-Type' => 'application/json'], $body));

    try {
        $client->listCashiers();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->pending)->toBeNull()
            ->and($e->apiErrorMessage)->toBe('Tax service (SRC) is temporarily unavailable.');
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
