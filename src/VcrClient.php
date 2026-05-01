<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Official PHP SDK for the VCR.AM Virtual Cash Register API.
 *
 * @see https://vcr.am
 */
final class VcrClient
{
    public const DEFAULT_BASE_URL = 'https://app.vcr.am';

    public const DEFAULT_TIMEOUT_MS = 30_000;

    public readonly string $baseUrl;

    public readonly int $timeoutMs;

    private readonly string $apiKey;

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly LoggerInterface $logger;

    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('apiKey must not be empty.');
        }

        if ($timeoutMs <= 0) {
            throw new InvalidArgumentException('timeoutMs must be positive.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeoutMs = $timeoutMs;
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->logger = $logger ?? new NullLogger();
    }
}
