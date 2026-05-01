<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Model\CashierListItem;
use BlobSolutions\VcrAm\Model\RegisterSaleResponse;
use BlobSolutions\VcrAm\Model\SaleDetail;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
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
    public const DEFAULT_BASE_URL = 'https://vcr.am/api/v1';

    public const DEFAULT_TIMEOUT_MS = 30_000;

    public const VERSION = '0.1.0-dev';

    public readonly string $baseUrl;

    public readonly int $timeoutMs;

    private readonly string $apiKey;

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly LoggerInterface $logger;

    private readonly TreeMapper $mapper;

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
        $this->mapper = (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->mapper();
    }

    /**
     * Lists every cashier registered for the authenticated VCR.AM account.
     *
     * @return list<CashierListItem>
     *
     * @throws VcrApiException        On non-2xx HTTP responses
     * @throws VcrNetworkException    On network/transport failures
     * @throws VcrValidationException On schema mismatches in the response body
     */
    public function listCashiers(): array
    {
        /** @var list<CashierListItem> $result */
        $result = $this->request(
            'GET',
            '/cashiers',
            'list<' . CashierListItem::class . '>',
        );

        return $result;
    }

    /**
     * Registers (fiscalises) a sale through the VCR.AM API. Returns the
     * SRC-issued identifiers required to display or share the receipt.
     *
     * @throws VcrApiException        On non-2xx HTTP responses (validation
     *                                rejections from the server, expired
     *                                tokens, etc.)
     * @throws VcrNetworkException    On network/transport failures
     * @throws VcrValidationException On schema mismatches in the response body
     */
    public function registerSale(RegisterSaleInput $input): RegisterSaleResponse
    {
        /** @var RegisterSaleResponse $result */
        $result = $this->request(
            'POST',
            '/sales',
            RegisterSaleResponse::class,
            $this->encodeJsonSerializable($input),
        );

        return $result;
    }

    /**
     * Reads back the full detail of a previously-registered sale.
     *
     * @param int $saleId The numeric sale id returned by
     *                    {@see self::registerSale()} as `RegisterSaleResponse::$saleId`.
     *
     * @throws InvalidArgumentException When `$saleId` is negative
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function getSale(int $saleId): SaleDetail
    {
        if ($saleId < 0) {
            throw new InvalidArgumentException('saleId must be non-negative.');
        }

        /** @var SaleDetail $result */
        $result = $this->request(
            'GET',
            sprintf('/sales/%d', $saleId),
            SaleDetail::class,
        );

        return $result;
    }

    /**
     * Sends a JSON request to the VCR.AM API and maps the response payload
     * onto the type described by `$signature` (a Valinor type DSL string,
     * e.g. `list<Foo>`, `array{id: int, name: string}`, or a class-string).
     *
     * @param non-empty-string                $method
     * @param non-empty-string                $path      Path relative to {@see $baseUrl}, beginning with `/`
     * @param non-empty-string                $signature Valinor type signature
     * @param array<string, mixed>|list<mixed>|null $jsonBody Body for POST/PUT requests, encoded as JSON
     *
     * @return mixed The mapped value (caller narrows via `@var` or `@return T`)
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    private function request(
        string $method,
        string $path,
        string $signature,
        ?array $jsonBody = null,
    ): mixed {
        $request = $this->buildRequest($method, $path, $jsonBody);

        $this->logger->debug('VCR.AM request', [
            'method' => $method,
            'url' => (string) $request->getUri(),
        ]);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->logger->warning('VCR.AM network failure', [
                'method' => $method,
                'url' => (string) $request->getUri(),
                'error' => $e->getMessage(),
            ]);

            throw new VcrNetworkException($request, $e);
        }

        $rawBody = (string) $response->getBody();
        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            [$errorCode, $errorMessage] = $this->extractApiError($rawBody);

            $this->logger->warning('VCR.AM API error', [
                'method' => $method,
                'url' => (string) $request->getUri(),
                'status' => $statusCode,
                'errorCode' => $errorCode,
                'errorMessage' => $errorMessage,
            ]);

            throw new VcrApiException(
                $statusCode,
                $errorCode,
                $errorMessage,
                $rawBody,
                $request,
                $response,
            );
        }

        try {
            $decoded = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new VcrValidationException(
                $rawBody,
                $request,
                $response,
                'response body is not valid JSON: ' . $e->getMessage(),
                $e,
            );
        }

        if (! is_array($decoded)) {
            throw new VcrValidationException(
                $rawBody,
                $request,
                $response,
                'expected JSON array or object at the response root, got ' . get_debug_type($decoded),
            );
        }

        try {
            return $this->mapper->map($signature, Source::array($decoded));
        } catch (MappingError $e) {
            throw new VcrValidationException(
                $rawBody,
                $request,
                $response,
                $e->getMessage(),
                $e,
            );
        }
    }

    /**
     * @param non-empty-string                       $method
     * @param non-empty-string                       $path
     * @param array<string, mixed>|list<mixed>|null  $jsonBody
     */
    private function buildRequest(string $method, string $path, ?array $jsonBody): RequestInterface
    {
        $url = $this->baseUrl . $path;

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', sprintf('vcr-am-sdk-php/%s (+https://github.com/blob-am/vcr-am-sdk-php)', self::VERSION));

        if ($jsonBody !== null) {
            try {
                $encoded = json_encode($jsonBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Failed to JSON-encode request body: ' . $e->getMessage(), 0, $e);
            }

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($encoded));
        }

        return $request;
    }

    /**
     * Encodes a top-level {@see JsonSerializable} input into the array shape
     * the {@see self::request()} helper expects. Nested JsonSerializable
     * values inside the array are left intact — `json_encode` resolves them
     * recursively when the request is built.
     *
     * @return array<string, mixed>
     */
    private function encodeJsonSerializable(JsonSerializable $input): array
    {
        $encoded = $input->jsonSerialize();

        if (! is_array($encoded)) {
            throw new LogicException(sprintf(
                '%s::jsonSerialize() must return an array; got %s.',
                $input::class,
                get_debug_type($encoded),
            ));
        }

        $result = [];
        foreach ($encoded as $key => $value) {
            if (! is_string($key)) {
                throw new LogicException(sprintf(
                    '%s::jsonSerialize() must return a string-keyed array; got int key %d.',
                    $input::class,
                    $key,
                ));
            }
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Extracts an error code and message from the raw error body. Returns
     * `[null, null]` when the body is not a JSON object or doesn't carry the
     * expected envelope.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractApiError(string $rawBody): array
    {
        try {
            $decoded = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [null, null];
        }

        if (! is_array($decoded)) {
            return [null, null];
        }

        $code = $decoded['code'] ?? null;
        $message = $decoded['message'] ?? null;

        return [
            is_string($code) ? $code : null,
            is_string($message) ? $message : null,
        ];
    }
}
