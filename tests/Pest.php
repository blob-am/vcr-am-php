<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\VcrClient;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;

uses()->in(__DIR__);

/**
 * Builds a fresh VcrClient wired to a PSR-18 mock client and PSR-17 factories.
 * Returns the client and its collaborators so tests can populate the mock with
 * canned responses and assert against the request the SDK builds.
 *
 * @return array{0: VcrClient, 1: MockClient, 2: Psr17Factory}
 */
function makeMockedClient(): array
{
    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $client = new VcrClient(
        apiKey: 'test-key',
        httpClient: $mockClient,
        requestFactory: $factory,
        streamFactory: $factory,
    );

    return [$client, $mockClient, $factory];
}
