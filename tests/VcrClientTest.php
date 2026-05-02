<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\VcrClient;

it('rejects an empty API key', function (): void {
    new VcrClient(apiKey: '');
})->throws(InvalidArgumentException::class, 'apiKey must not be empty.');

it('rejects a whitespace-only API key', function (): void {
    new VcrClient(apiKey: '   ');
})->throws(InvalidArgumentException::class, 'apiKey must not be empty.');

it('strips a trailing slash from baseUrl', function (): void {
    $client = new VcrClient(apiKey: 'test-key', baseUrl: 'https://vcr.am/api/v1/');

    expect($client->baseUrl)->toBe('https://vcr.am/api/v1');
});

it('uses the default baseUrl when none is provided', function (): void {
    $client = new VcrClient(apiKey: 'test-key');

    expect($client->baseUrl)->toBe(VcrClient::DEFAULT_BASE_URL);
});
