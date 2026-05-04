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
            $repository = $container->make('config');
            $apiKey = $repository->get('vcr-am.api_key');
            $baseUrl = $repository->get('vcr-am.base_url');

            if (! is_string($apiKey) || trim($apiKey) === '') {
                throw new RuntimeException(
                    'VCR.AM: missing or empty `vcr-am.api_key`. Set VCR_AM_API_KEY in your .env, '
                    . 'or run `php artisan vendor:publish --tag=vcr-am-config` and edit `config/vcr-am.php`.',
                );
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
        });
    }
}
