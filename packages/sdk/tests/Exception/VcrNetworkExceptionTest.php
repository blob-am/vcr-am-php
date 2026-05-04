<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;

it('extends the abstract VcrException base', function (): void {
    $factory = new Psr17Factory();
    $exception = new VcrNetworkException(
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        previous: new RuntimeException('Connection timed out'),
    );

    expect($exception)->toBeInstanceOf(VcrException::class);
});

it('embeds the cause into the message and chains the previous throwable', function (): void {
    $factory = new Psr17Factory();
    $cause = new RuntimeException('Could not resolve host: vcr.am');
    $exception = new VcrNetworkException(
        request: $factory->createRequest('GET', 'https://vcr.am/api/v1/cashiers'),
        previous: $cause,
    );

    expect($exception->getMessage())
        ->toBe('VCR.AM API request failed at the network/transport layer: Could not resolve host: vcr.am')
        ->and($exception->getPrevious())->toBe($cause);
});

it('preserves the request for retry/inspection', function (): void {
    $factory = new Psr17Factory();
    $request = $factory->createRequest('POST', 'https://vcr.am/api/v1/sale');
    $exception = new VcrNetworkException(
        request: $request,
        previous: new RuntimeException('TLS handshake failed'),
    );

    expect($exception->request)->toBe($request);
});
