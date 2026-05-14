<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm;

use BlobSolutions\LaravelVcrAm\Console\HealthCheckCommand;
use BlobSolutions\VcrAm\VcrClient;
use Illuminate\Contracts\Container\Container;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class VcrAmServiceProvider extends PackageServiceProvider
{
    /**
     * Container binding identifier for the parallel sandbox client.
     *
     * Resolve with `app(VcrAmServiceProvider::SANDBOX_BINDING)` or via the
     * `VcrAm::sandbox()` facade method. The binding only exists when
     * `vcr-am.sandbox_api_key` is configured — otherwise resolving it
     * throws a clear `RuntimeException` instead of silently falling back
     * to the production key.
     */
    public const SANDBOX_BINDING = 'vcr-am.sandbox';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('vcr-am')
            ->hasConfigFile()
            ->hasCommand(HealthCheckCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(VcrClient::class, static function (Container $container): VcrClient {
            return self::buildClient($container, channel: 'production');
        });

        $this->app->singleton(self::SANDBOX_BINDING, static function (Container $container): VcrClient {
            return self::buildClient($container, channel: 'sandbox');
        });
    }

    /**
     * Builds a `VcrClient` for one channel — either the default ("production")
     * binding or the sandbox binding. Centralised so both clients share
     * identical handling of base URL, optional HTTP client / factory bindings,
     * and the optional PSR-3 logger forwarding.
     *
     * @param 'production'|'sandbox' $channel
     */
    private static function buildClient(Container $container, string $channel): VcrClient
    {
        $repository = $container->make('config');
        $configKey = $channel === 'production' ? 'vcr-am.api_key' : 'vcr-am.sandbox_api_key';
        $apiKey = $repository->get($configKey);
        $baseUrl = $repository->get('vcr-am.base_url');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            $envVar = $channel === 'production' ? 'VCR_AM_API_KEY' : 'VCR_AM_SANDBOX_API_KEY';

            throw new RuntimeException(sprintf(
                'VCR.AM: missing or empty `%s`. Set %s in your .env, '
                . 'or run `php artisan vendor:publish --tag=vcr-am-config` and edit `config/vcr-am.php`.',
                $configKey,
                $envVar,
            ));
        }

        if ($baseUrl !== null && ! is_string($baseUrl)) {
            throw new RuntimeException('VCR.AM: `vcr-am.base_url` must be a string or null.');
        }

        // Treat empty / whitespace as "unset" so VCR_AM_BASE_URL= in .env
        // falls through to the SDK default instead of producing requests
        // against an empty host.
        if (is_string($baseUrl) && trim($baseUrl) === '') {
            $baseUrl = null;
        }

        return new VcrClient(
            apiKey: $apiKey,
            baseUrl: $baseUrl ?? VcrClient::DEFAULT_BASE_URL,
            httpClient: $container->bound(ClientInterface::class)
                ? $container->make(ClientInterface::class)
                : null,
            requestFactory: $container->bound(RequestFactoryInterface::class)
                ? $container->make(RequestFactoryInterface::class)
                : null,
            streamFactory: $container->bound(StreamFactoryInterface::class)
                ? $container->make(StreamFactoryInterface::class)
                : null,
            logger: $container->bound(LoggerInterface::class)
                ? $container->make(LoggerInterface::class)
                : null,
        );
    }
}
