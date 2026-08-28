<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrException;
use BlobSolutions\VcrAm\Model\ApiErrorIssue;
use BlobSolutions\VcrAm\Model\PendingResource;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

it('extends the abstract VcrException base', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 500,
        apiErrorMessage: null,
        rawBody: '',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(500),
    );

    expect($exception)->toBeInstanceOf(VcrException::class);
});

it('formats the message with the status code and the error text', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 422,
        apiErrorMessage: 'TIN must be 8 or 10 digits',
        rawBody: '{}',
        request: $factory->createRequest('POST', 'https://vcr.am/api/v1/sales'),
        response: new Response(422),
    );

    expect($exception->getMessage())
        ->toBe('VCR.AM API returned HTTP 422: TIN must be 8 or 10 digits');
});

it('formats the message without optional error fields', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 503,
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
    $rawBody = '{"error":"Sale not found"}';
    $exception = new VcrApiException(
        statusCode: 404,
        apiErrorMessage: 'Sale not found',
        rawBody: $rawBody,
        request: $request,
        response: $response,
    );

    expect($exception->statusCode)->toBe(404)
        ->and($exception->apiErrorMessage)->toBe('Sale not found')
        ->and($exception->rawBody)->toBe($rawBody)
        ->and($exception->request)->toBe($request)
        ->and($exception->response)->toBe($response)
        ->and($exception->response->getHeaderLine('X-Trace-Id'))->toBe('abc-123');
});

it('defaults the envelope extras to empty rather than requiring them', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 500,
        apiErrorMessage: 'boom',
        rawBody: '{"error":"boom"}',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(500),
    );

    expect($exception->issues)->toBe([])
        ->and($exception->requestId)->toBeNull()
        ->and($exception->pending)->toBeNull();
});

it('carries the envelope extras when the server sends them', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrApiException(
        statusCode: 502,
        apiErrorMessage: 'Tax service (SRC) is temporarily unavailable',
        rawBody: '{}',
        request: $factory->createRequest('POST', 'https://vcr.am/api/v1/sales'),
        response: new Response(502),
        issues: [new ApiErrorIssue(['items', 0, 'price'], 'Required', 'invalid_type')],
        requestId: '3f0c1c8e-1f2a-4c9e-9b1d-2b7a4c8e5f10',
        pending: new PendingResource('sale', 5122, '/api/v1/sales/5122'),
    );

    $issue = $exception->issues[0] ?? null;

    expect($issue?->pointer())->toBe('items.0.price')
        ->and($exception->requestId)->toBe('3f0c1c8e-1f2a-4c9e-9b1d-2b7a4c8e5f10')
        ->and($exception->pending?->id)->toBe(5122)
        ->and($exception->pending?->statusUrl)->toBe('/api/v1/sales/5122');
});
