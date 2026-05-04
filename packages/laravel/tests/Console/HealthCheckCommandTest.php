<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\VcrClient;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

beforeEach(function (): void {
    $this->mockClient = new MockClient();
    $factory = new Psr17Factory();

    $this->app->instance(ClientInterface::class, $this->mockClient);
    $this->app->instance(RequestFactoryInterface::class, $factory);
    $this->app->instance(StreamFactoryInterface::class, $factory);
    $this->app->forgetInstance(VcrClient::class);
});

it('reports the cashier count and exits successfully when the API responds with cashiers', function (): void {
    $body = json_encode([
        ['deskId' => 'd1', 'internalId' => 1, 'name' => ['en' => ['language' => 'en', 'content' => 'A']]],
        ['deskId' => 'd2', 'internalId' => 2, 'name' => ['en' => ['language' => 'en', 'content' => 'B']]],
    ], JSON_THROW_ON_ERROR);

    $this->mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $this->artisan('vcr-am:health')
        ->expectsOutputToContain('VCR.AM API reachable. Found 2 cashier(s).')
        ->assertSuccessful();
});

it('handles an empty cashier list without errors', function (): void {
    $this->mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $this->artisan('vcr-am:health')
        ->expectsOutputToContain('Found 0 cashier(s).')
        ->assertSuccessful();
});

it('reports failure with the SDK exception message when the API rejects the request', function (): void {
    $errorBody = json_encode(['errorCode' => 'UNAUTHORIZED', 'message' => 'Bad API key'], JSON_THROW_ON_ERROR);
    $this->mockClient->addResponse(new Response(401, ['Content-Type' => 'application/json'], $errorBody));

    $this->artisan('vcr-am:health')
        ->expectsOutputToContain('VCR.AM API check failed:')
        ->assertExitCode(1);
});
