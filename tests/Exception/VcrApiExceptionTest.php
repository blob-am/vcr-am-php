<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

it('extends the abstract VcrException base', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 500,
        apiErrorCode: null,
        apiErrorMessage: null,
        rawBody: '',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(500),
    );

    expect($exception)->toBeInstanceOf(VcrException::class);
});

it('formats the message with status code, error code and message', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 422,
        apiErrorCode: 'INVALID_TIN',
        apiErrorMessage: 'TIN must be 8 or 10 digits',
        rawBody: '{}',
        request: $factory->createRequest('POST', 'https://vcr.am/api/v1/sale'),
        response: new Response(422),
    );

    expect($exception->getMessage())
        ->toBe('VCR.AM API returned HTTP 422 [INVALID_TIN]: TIN must be 8 or 10 digits');
});

it('formats the message without optional error fields', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 503,
        apiErrorCode: null,
        apiErrorMessage: null,
        rawBody: '<html>...</html>',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(503),
    );

    expect($exception->getMessage())->toBe('VCR.AM API returned HTTP 503');
});

it('preserves the raw body, request and response for inspection', function (): void {
    $factory = new Psr17Factory();
    $request = $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers');
    $response = new Response(404, ['X-Trace-Id' => 'abc-123']);
    $exception = new VcrApiException(
        statusCode: 404,
        apiErrorCode: 'NOT_FOUND',
        apiErrorMessage: null,
        rawBody: '{"code":"NOT_FOUND"}',
        request: $request,
        response: $response,
    );

    expect($exception->statusCode)->toBe(404)
        ->and($exception->apiErrorCode)->toBe('NOT_FOUND')
        ->and($exception->rawBody)->toBe('{"code":"NOT_FOUND"}')
        ->and($exception->request)->toBe($request)
        ->and($exception->response)->toBe($response)
        ->and($exception->response->getHeaderLine('X-Trace-Id'))->toBe('abc-123');
});
