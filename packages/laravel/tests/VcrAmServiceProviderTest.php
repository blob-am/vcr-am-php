<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use Http\Mock\Client as MockClient;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stringable;

it('binds VcrClient as a singleton in the container', function (): void {
    $first = $this->app->make(VcrClient::class);
    $second = $this->app->make(VcrClient::class);

    expect($first)->toBeInstanceOf(VcrClient::class);
    expect($first)->toBe($second);
});

it('reads api key and base url from the vcr-am config', function (): void {
    // Asserted on the request the client actually sends rather than on its
    // private state: the key only matters if it reaches the wire, and the
    // SDK is free to keep it wherever it likes.
    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $this->app->instance(ClientInterface::class, $mockClient);
    $this->app->instance(RequestFactoryInterface::class, $factory);
    $this->app->instance(StreamFactoryInterface::class, $factory);

    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.api_key', 'a-different-key');
    config()->set('vcr-am.base_url', 'https://override.example/api');

    $client = $this->app->make(VcrClient::class);
    $mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));
    $client->listCashiers();

    $request = $mockClient->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getHeaderLine('X-API-Key'))->toBe('a-different-key')
        ->and((string) $request->getUri())->toBe('https://override.example/api/cashiers')
        ->and($client->baseUrl)->toBe('https://override.example/api');
});

it('falls back to the SDK default base url when the config value is null', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.base_url', null);

    $client = $this->app->make(VcrClient::class);

    $reflection = new ReflectionObject($client);

    expect($reflection->getProperty('baseUrl')->getValue($client))->toBe(VcrClient::DEFAULT_BASE_URL);
});

it('falls back to the SDK default base url when the config value is an empty string', function (): void {
    // Real-world trigger: VCR_AM_BASE_URL= in .env. Laravel's env() returns ''
    // (not null), and a literal empty baseUrl would silently break every
    // outbound request — surface as default instead.
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.base_url', '');

    $client = $this->app->make(VcrClient::class);

    $reflection = new ReflectionObject($client);

    expect($reflection->getProperty('baseUrl')->getValue($client))->toBe(VcrClient::DEFAULT_BASE_URL);
});

it('falls back to the SDK default base url when the config value is whitespace only', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.base_url', '   ');

    $client = $this->app->make(VcrClient::class);

    $reflection = new ReflectionObject($client);

    expect($reflection->getProperty('baseUrl')->getValue($client))->toBe(VcrClient::DEFAULT_BASE_URL);
});

it('throws when the api key is missing', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.api_key', null);

    expect(fn () => $this->app->make(VcrClient::class))
        ->toThrow(RuntimeException::class, 'missing or empty');
});

it('throws when the api key is whitespace only', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.api_key', '   ');

    expect(fn () => $this->app->make(VcrClient::class))
        ->toThrow(RuntimeException::class, 'missing or empty');
});

it('throws when the base url is not a string or null', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.base_url', 12345);

    expect(fn () => $this->app->make(VcrClient::class))
        ->toThrow(RuntimeException::class, 'must be a string or null');
});

it('passes the PSR-3 logger bound by Laravel into the SDK client', function (): void {
    // Same reasoning as the api-key test: what matters is that the logger
    // Laravel bound is the one the SDK writes to, which only a real call
    // can show.
    $logger = new class () extends NullLogger {
        /** @var list<string> */
        public array $messages = [];

        /**
         * @param array<string, mixed> $context
         */
        public function log(mixed $level, string|Stringable $message, array $context = []): void
        {
            $this->messages[] = (string) $message;
        }
    };

    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $this->app->instance(ClientInterface::class, $mockClient);
    $this->app->instance(RequestFactoryInterface::class, $factory);
    $this->app->instance(StreamFactoryInterface::class, $factory);
    $this->app->instance(LoggerInterface::class, $logger);

    $this->app->forgetInstance(VcrClient::class);

    $client = $this->app->make(VcrClient::class);
    $mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));
    $client->listCashiers();

    expect($logger->messages)->toContain('VCR.AM request');
});

it('publishes the config file under the vcr-am-config tag', function (): void {
    $expectedSource = realpath(__DIR__ . '/../config/vcr-am.php');
    $expectedTarget = $this->app->configPath('vcr-am.php');

    $paths = ServiceProvider::pathsToPublish(VcrAmServiceProvider::class, 'vcr-am-config');

    expect($paths)->toHaveCount(1);

    $sources = array_map(realpath(...), array_keys($paths));
    $targets = array_values($paths);

    expect($sources)->toContain($expectedSource);
    expect($targets)->toContain($expectedTarget);
});

it('registers the vcr-am:health artisan command', function (): void {
    /** @var Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = $this->app->make(Illuminate\Contracts\Console\Kernel::class);

    expect(array_keys($kernel->all()))->toContain('vcr-am:health');
});
