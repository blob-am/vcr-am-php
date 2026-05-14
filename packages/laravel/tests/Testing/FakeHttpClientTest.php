<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\Testing\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;

it('records the path verbatim when the request URI does not sit under /api/v1', function (): void {
    // Direct unit test: the SDK always prefixes /api/v1, but a custom
    // baseUrl override (or any non-SDK consumer of FakeHttpClient) would
    // exercise this branch. We hit it directly with a synthetic request.
    $fake = new FakeHttpClient();
    $fake->stub('GET /custom', static fn () => FakeHttpClient::jsonResponse([]));

    $request = (new Psr17Factory())->createRequest('GET', 'https://example.com/custom');
    $fake->sendRequest($request);

    expect($fake->recorded()[0]->path)->toBe('/custom');
});

it('records decodedBody as null when the request body is not valid JSON', function (): void {
    $fake = new FakeHttpClient();
    $fake->stub('POST /custom', static fn () => FakeHttpClient::jsonResponse([]));

    $factory = new Psr17Factory();
    $request = $factory->createRequest('POST', 'https://example.com/custom')
        ->withBody($factory->createStream('{not json'));

    $fake->sendRequest($request);

    $entry = $fake->recorded()[0];
    expect($entry->rawBody)->toBe('{not json')
        ->and($entry->decodedBody)->toBeNull();
});

it('records decodedBody as null when the JSON root is a scalar', function (): void {
    $fake = new FakeHttpClient();
    $fake->stub('POST /custom', static fn () => FakeHttpClient::jsonResponse([]));

    $factory = new Psr17Factory();
    $request = $factory->createRequest('POST', 'https://example.com/custom')
        ->withBody($factory->createStream('"a string root"'));

    $fake->sendRequest($request);

    expect($fake->recorded()[0]->decodedBody)->toBeNull();
});

it('jsonResponse accepts a non-default status so stubs can simulate 4xx errors', function (): void {
    $response = FakeHttpClient::jsonResponse(['code' => 'X'], 422);

    expect($response->getStatusCode())->toBe(422)
        ->and((string) $response->getBody())->toContain('"code":"X"');
});
