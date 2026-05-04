<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

it('binds VcrClient as a singleton in the container', function (): void {
    $first = $this->app->make(VcrClient::class);
    $second = $this->app->make(VcrClient::class);

    expect($first)->toBeInstanceOf(VcrClient::class);
    expect($first)->toBe($second);
});

it('reads api key and base url from the vcr-am config', function (): void {
    $this->app->forgetInstance(VcrClient::class);
    config()->set('vcr-am.api_key', 'a-different-key');
    config()->set('vcr-am.base_url', 'https://override.example/api');

    $client = $this->app->make(VcrClient::class);

    $reflection = new ReflectionObject($client);

    expect($reflection->getProperty('apiKey')->getValue($client))->toBe('a-different-key');
    expect($reflection->getProperty('baseUrl')->getValue($client))->toBe('https://override.example/api');
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
    $this->app->forgetInstance(VcrClient::class);

    $client = $this->app->make(VcrClient::class);

    $reflection = new ReflectionObject($client);
    $logger = $reflection->getProperty('logger')->getValue($client);

    expect($logger)
        ->toBeInstanceOf(LoggerInterface::class)
        ->not->toBeInstanceOf(NullLogger::class);
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
