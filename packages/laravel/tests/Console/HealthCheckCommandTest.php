<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use Http\Mock\Client as MockClient;
use Illuminate\Support\Facades\Artisan;
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
    $this->app->forgetInstance(VcrAmServiceProvider::SANDBOX_BINDING);
});

/**
 * Drives the artisan command and returns its captured stdout. We bypass
 * `$this->artisan()` because that helper checks expectations line-by-line,
 * and the health-check output is one dense line containing every datum we
 * want to assert on.
 *
 * @param  array<string, mixed>  $params
 */
function runHealth(array $params = []): array
{
    $exitCode = Artisan::call('vcr-am:health', $params);

    return [$exitCode, Artisan::output()];
}

it('reports the VCR identity and exits successfully', function (): void {
    $body = json_encode([
        'vcrId' => 42,
        'crn' => '1234567890123',
        'mode' => 'production',
        'tradingPlatformName' => 'My Shop',
        'businessEntity' => ['tin' => '01234567', 'name' => 'My Shop LLC'],
    ], JSON_THROW_ON_ERROR);

    $this->mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    [$exitCode, $output] = runHealth();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"My Shop"')
        ->and($output)->toContain('production')
        ->and($output)->toContain('CRN 1234567890123')
        ->and($output)->toContain('My Shop LLC')
        ->and($output)->toContain('TIN 01234567');
});

it('renders placeholders for an unactivated VCR and an unnamed entity', function (): void {
    $body = json_encode([
        'vcrId' => 100,
        'crn' => null,
        'mode' => 'sandbox',
        'tradingPlatformName' => '',
        'businessEntity' => ['tin' => '01234567', 'name' => ''],
    ], JSON_THROW_ON_ERROR);

    $this->mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    [$exitCode, $output] = runHealth();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('(unnamed VCR)')
        ->and($output)->toContain('sandbox')
        ->and($output)->toContain('CRN not activated')
        ->and($output)->toContain('(unnamed entity)');
});

it('hits the sandbox client when --sandbox is passed', function (): void {
    config()->set('vcr-am.sandbox_api_key', 'sandbox-key');

    $body = json_encode([
        'vcrId' => 7,
        'crn' => null,
        'mode' => 'sandbox',
        'tradingPlatformName' => 'Sandbox VCR',
        'businessEntity' => ['tin' => '01234567', 'name' => 'My Shop LLC'],
    ], JSON_THROW_ON_ERROR);

    $this->mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    [$exitCode, $output] = runHealth(['--sandbox' => true]);

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('"Sandbox VCR"')
        ->and($output)->toContain('sandbox');

    $request = $this->mockClient->getLastRequest();
    expect($request->getHeaderLine('X-API-Key'))->toBe('sandbox-key');
});

it('reports failure with the SDK exception message when the API rejects the request', function (): void {
    $errorBody = json_encode(['errorCode' => 'UNAUTHORIZED', 'message' => 'Bad API key'], JSON_THROW_ON_ERROR);
    $this->mockClient->addResponse(new Response(401, ['Content-Type' => 'application/json'], $errorBody));

    [$exitCode, $output] = runHealth();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('VCR.AM API check failed:');
});
