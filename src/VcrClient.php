<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Input\CreateCashierInput;
use BlobSolutions\VcrAm\Input\CreateDepartmentInput;
use BlobSolutions\VcrAm\Input\CreateOfferInput;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentInput;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\RegisterSaleRefundInput;
use BlobSolutions\VcrAm\Model\CashierListItem;
use BlobSolutions\VcrAm\Model\ClassifierSearchItem;
use BlobSolutions\VcrAm\Model\CreateCashierResponse;
use BlobSolutions\VcrAm\Model\CreateDepartmentResponse;
use BlobSolutions\VcrAm\Model\CreateOfferResponse;
use BlobSolutions\VcrAm\Model\PrepaymentDetail;
use BlobSolutions\VcrAm\Model\RegisterPrepaymentRefundResponse;
use BlobSolutions\VcrAm\Model\RegisterPrepaymentResponse;
use BlobSolutions\VcrAm\Model\RegisterSaleRefundResponse;
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

    public const VERSION = '0.1.0';

    /**
     * Cap on how many bytes of an error response body are included in the
     * SDK's structured warning log. The full body is still preserved on
     * `VcrApiException::$rawBody` for callers that need the unabridged payload.
     */
    private const ERROR_BODY_PREVIEW_BYTES = 500;

    public readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    private readonly LoggerInterface $logger;

    private readonly TreeMapper $mapper;

    public function __construct(
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('apiKey must not be empty.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
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
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Registers a refund against a previously-registered sale. Pass `null`
     * for `RegisterSaleRefundInput::$items` to refund the entire sale, or a
     * partial list of {@see Input\RefundItemInput}.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function registerSaleRefund(RegisterSaleRefundInput $input): RegisterSaleRefundResponse
    {
        /** @var RegisterSaleRefundResponse $result */
        $result = $this->request(
            'POST',
            '/sales/refund',
            RegisterSaleRefundResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Registers a prepayment — an advance payment from a buyer that will be
     * redeemed against a future sale.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function registerPrepayment(RegisterPrepaymentInput $input): RegisterPrepaymentResponse
    {
        /** @var RegisterPrepaymentResponse $result */
        $result = $this->request(
            'POST',
            '/prepayments',
            RegisterPrepaymentResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Reads back the full detail of a previously-registered prepayment.
     *
     * @throws InvalidArgumentException When `$prepaymentId` is negative
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function getPrepayment(int $prepaymentId): PrepaymentDetail
    {
        if ($prepaymentId < 0) {
            throw new InvalidArgumentException('prepaymentId must be non-negative.');
        }

        /** @var PrepaymentDetail $result */
        $result = $this->request(
            'GET',
            sprintf('/prepayments/%d', $prepaymentId),
            PrepaymentDetail::class,
        );

        return $result;
    }

    /**
     * Refunds a previously-registered prepayment in full. There is no
     * partial-refund variant — a prepayment is an indivisible advance
     * amount and is either kept or fully reversed.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function registerPrepaymentRefund(RegisterPrepaymentRefundInput $input): RegisterPrepaymentRefundResponse
    {
        /** @var RegisterPrepaymentRefundResponse $result */
        $result = $this->request(
            'POST',
            '/prepayments/refund',
            RegisterPrepaymentRefundResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Creates a new cashier under the authenticated VCR.AM account.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function createCashier(CreateCashierInput $input): CreateCashierResponse
    {
        /** @var CreateCashierResponse $result */
        $result = $this->request(
            'POST',
            '/cashiers',
            CreateCashierResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Creates a new department under the authenticated VCR.AM account.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function createDepartment(CreateDepartmentInput $input): CreateDepartmentResponse
    {
        /** @var CreateDepartmentResponse $result */
        $result = $this->request(
            'POST',
            '/departments',
            CreateDepartmentResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Creates a new offer (product or service) on the account.
     *
     * Distinct from referencing an offer inline inside a sale item via
     * {@see Input\Offer::createNew()}: that's a
     * convenience for "register the sale and the offer in one call",
     * while this endpoint creates a standalone catalogue entry.
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function createOffer(CreateOfferInput $input): CreateOfferResponse
    {
        /** @var CreateOfferResponse $result */
        $result = $this->request(
            'POST',
            '/offers',
            CreateOfferResponse::class,
            $input->jsonSerialize(),
        );

        return $result;
    }

    /**
     * Searches the SRC classifier (product/service taxonomy) for entries
     * matching `$query` in the given language. Returns at most a handful
     * of fuzzy-matched items — typically used to populate an offer-creation
     * autocomplete in a UI.
     *
     * @return list<ClassifierSearchItem>
     *
     * @throws InvalidArgumentException When `$query` is empty, or when
     *                                  `$language` is {@see Language::Multi}
     *                                  (the search index has no Multi entries)
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function searchClassifier(string $query, OfferType $type, Language $language): array
    {
        $trimmedQuery = trim($query);

        if ($trimmedQuery === '') {
            throw new InvalidArgumentException('query must not be empty.');
        }

        if ($language === Language::Multi) {
            throw new InvalidArgumentException('language must be a concrete language (hy/ru/en); Multi is not searchable.');
        }

        /** @var list<ClassifierSearchItem> $result */
        $result = $this->request(
            'GET',
            '/searchByClassifier',
            'list<' . ClassifierSearchItem::class . '>',
            null,
            [
                'query' => $trimmedQuery,
                'type' => $type->value,
                'language' => $language->value,
            ],
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
     * @param non-empty-string                       $method
     * @param non-empty-string                       $path      Path relative to {@see $baseUrl}, beginning with `/`
     * @param non-empty-string                       $signature Valinor type signature
     * @param array<string, mixed>|list<mixed>|null  $jsonBody  Body for POST/PUT requests, encoded as JSON
     * @param ?array<string, string>                 $query     Query string parameters, RFC 3986-encoded
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
        ?array $query = null,
    ): mixed {
        $request = $this->buildRequest($method, $path, $jsonBody, $query);

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

            throw new VcrNetworkException($this->redactRequest($request), $e);
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
                'rawBodyPreview' => mb_substr($rawBody, 0, self::ERROR_BODY_PREVIEW_BYTES),
            ]);

            throw new VcrApiException(
                $statusCode,
                $errorCode,
                $errorMessage,
                $rawBody,
                $this->redactRequest($request),
                $response,
            );
        }

        try {
            $decoded = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new VcrValidationException(
                $rawBody,
                $this->redactRequest($request),
                $response,
                'response body is not valid JSON: ' . $e->getMessage(),
                $e,
            );
        }

        if (! is_array($decoded)) {
            throw new VcrValidationException(
                $rawBody,
                $this->redactRequest($request),
                $response,
                'expected JSON array or object at the response root, got ' . get_debug_type($decoded),
            );
        }

        try {
            return $this->mapper->map($signature, Source::array($decoded));
        } catch (MappingError $e) {
            throw new VcrValidationException(
                $rawBody,
                $this->redactRequest($request),
                $response,
                $e->getMessage(),
                $e,
            );
        }
    }

    /**
     * Strips secret-bearing headers from the request before it gets attached
     * to a public-facing exception. APMs and loggers that introspect
     * exception state (Sentry, Bugsnag, Laravel's verbose handler) routinely
     * dump request headers — we don't want the bearer token in those breadcrumbs.
     */
    private function redactRequest(RequestInterface $request): RequestInterface
    {
        return $request->withoutHeader('Authorization');
    }

    /**
     * @param non-empty-string                       $method
     * @param non-empty-string                       $path
     * @param array<string, mixed>|list<mixed>|null  $jsonBody
     * @param ?array<string, string>                 $query
     */
    private function buildRequest(string $method, string $path, ?array $jsonBody, ?array $query = null): RequestInterface
    {
        $url = $this->baseUrl . $path;

        if ($query !== null && $query !== []) {
            // Internal paths never carry their own query string, so unconditionally
            // prepend `?`. RFC 3986 encoding makes UTF-8 query values (e.g.
            // Armenian) round-trip cleanly through the API.
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', sprintf('vcr-am-sdk-php/%s (+https://github.com/blob-am/vcr-am-sdk-php)', self::VERSION));

        if ($jsonBody !== null) {
            // JSON_THROW_ON_ERROR surfaces unencodable input (NaN, INF,
            // resources, recursive structures) as a JsonException — the
            // caller's `$jsonBody` would have to be programmatically wrong
            // for that to fire, so we let it propagate untransformed.
            $encoded = json_encode($jsonBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($encoded));
        }

        return $request;
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
