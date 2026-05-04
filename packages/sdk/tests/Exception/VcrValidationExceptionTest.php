<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

it('extends the abstract VcrException base', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrValidationException(
        rawBody: 'not json',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(200),
        detail: 'response body is not valid JSON',
    );

    expect($exception)->toBeInstanceOf(VcrException::class);
});

it('includes the detail in the message', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrValidationException(
        rawBody: '{"unexpected":true}',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: new Response(200),
        detail: 'Could not map list<CashierListItem>',
    );

    expect($exception->getMessage())
        ->toBe('VCR.AM API response did not match the expected schema: Could not map list<CashierListItem>');
});

it('preserves the raw body and the optional response for inspection', function (): void {
    $factory = new Psr17Factory();
    $request = $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers');
    $response = new Response(200, [], '{}');
    $exception = new VcrValidationException(
        rawBody: '{}',
        request: $request,
        response: $response,
        detail: 'expected list, got object',
    );

    expect($exception->rawBody)->toBe('{}')
        ->and($exception->request)->toBe($request)
        ->and($exception->response)->toBe($response)
        ->and($exception->detail)->toBe('expected list, got object');
});

it('accepts a null response when validation happens before the body arrives', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrValidationException(
        rawBody: '',
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        response: null,
        detail: 'no response received',
    );

    expect($exception->response)->toBeNull();
});
