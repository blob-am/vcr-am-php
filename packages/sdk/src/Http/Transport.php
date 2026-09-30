<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Http;

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * One HTTP call to the VCR.AM API: build, send, classify the failure, map the
 * body onto a type.
 *
 * Extracted from {@see \BlobSolutions\VcrAm\VcrClient} when pairing arrived.
 * Pairing needs the same error vocabulary — a 4xx is a `VcrApiException` with
 * the server's own message, a socket failure is a `VcrNetworkException`
 * carrying a redacted request, a body that does not fit is a
 * `VcrValidationException` — but it happens *before* a store has an API key,
 * so it cannot go on a client whose whole premise is that it holds one. Hence
 * `$apiKey` is nullable here: null means send no `X-API-Key` header at all,
 * which is what an unauthenticated endpoint expects.
 *
 * @internal Not part of the SDK's public API. Construct a
 *           {@see \BlobSolutions\VcrAm\VcrClient} or a
 *           {@see \BlobSolutions\VcrAm\PairingClient} instead.
 */
final class Transport
{
    /**
     * Cap on the `$integration` product token. Long enough for a plugin name,
     * its version and a short parenthesised comment naming the host platform.
     */
    public const MAX_INTEGRATION_LENGTH = 200;

    /**
     * Cap on how many bytes of an error response body are included in the
     * structured warning log. The full body is still preserved on
     * `VcrApiException::$rawBody` for callers that need the unabridged payload.
     */
    private const ERROR_BODY_PREVIEW_BYTES = 500;

    public readonly string $baseUrl;

    private readonly TreeMapper $mapper;

    /**
     * @param ?string $apiKey      Null for endpoints that take no key, e.g. pairing
     * @param ?string $integration Already normalized by {@see normalizeIntegration}
     */
    public function __construct(
        string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $sdkVersion,
        private readonly ?string $integration,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->mapper = (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->mapper();
    }

    /**
     * Sends a request and maps the decoded response body onto the type
     * described by `$signature` (a Valinor type DSL string, e.g. `list<Foo>`,
     * `array{id: int, name: string}`, or a class-string).
     *
     * @param non-empty-string                      $method
     * @param non-empty-string                      $path      Path relative to {@see $baseUrl}, beginning with `/`
     * @param non-empty-string                      $signature Valinor type signature
     * @param array<string, mixed>|list<mixed>|null $jsonBody  Body for POST/PUT requests, encoded as JSON
     * @param ?array<string, string>                $query     Query string parameters, RFC 3986-encoded
     *
     * @return mixed The mapped value (caller narrows via `@var` or `@return T`)
     *
     * @throws VcrApiException
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function request(
        string $method,
        string $path,
        string $signature,
        ?array $jsonBody = null,
        ?array $query = null,
        ?string $idempotencyKey = null,
    ): mixed {
        $request = $this->buildRequest($method, $path, $jsonBody, $query, $idempotencyKey);

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
            $apiError = ErrorEnvelope::parse($rawBody);

            $this->logger->warning('VCR.AM API error', [
                'method' => $method,
                'url' => (string) $request->getUri(),
                'status' => $statusCode,
                'errorMessage' => $apiError->message,
                'requestId' => $apiError->requestId,
                // Worth its own log line: it is the difference between "the
                // call failed" and "the call failed but the receipt exists".
                'pendingId' => $apiError->pending?->id,
                'rawBodyPreview' => mb_substr($rawBody, 0, self::ERROR_BODY_PREVIEW_BYTES),
            ]);

            throw new VcrApiException(
                $statusCode,
                $apiError->message,
                $rawBody,
                $this->redactRequest($request),
                $response,
                $apiError->issues,
                $apiError->requestId,
                $apiError->pending,
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
     * Rejects anything that cannot legally sit in a header value, rather than
     * handing it to the PSR-7 implementation and hoping that one validates.
     * A product token is printable US-ASCII; a newline in there is header
     * injection, and the value comes from a plugin's own metadata, which on
     * WordPress is whatever a theme or another plugin last filtered it to.
     */
    public static function normalizeIntegration(string $integration): string
    {
        $trimmed = trim($integration);

        if ($trimmed === '' || mb_strlen($trimmed) > self::MAX_INTEGRATION_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'integration must be 1-%d characters, got %d.',
                self::MAX_INTEGRATION_LENGTH,
                mb_strlen($trimmed),
            ));
        }

        // \z, not $: PCRE's $ also matches before a trailing newline, which is
        // the one character this check exists to refuse.
        if (preg_match('/\A[\x20-\x7E]+\z/', $trimmed) !== 1) {
            throw new InvalidArgumentException(
                'integration must contain printable ASCII only, e.g. "my-plugin/1.2.3 (Platform/4.5)".',
            );
        }

        return $trimmed;
    }

    /**
     * Strips secret-bearing headers from the request before it gets attached
     * to a public-facing exception. APMs and loggers that introspect
     * exception state (Sentry, Bugsnag, Laravel's verbose handler) routinely
     * dump request headers — we don't want the API key in those breadcrumbs.
     */
    private function redactRequest(RequestInterface $request): RequestInterface
    {
        return $request->withoutHeader('X-API-Key');
    }

    /**
     * The SDK's own product token first, then the embedding integration's, in
     * the order RFC 9110 asks for: most significant software first.
     */
    private function userAgent(): string
    {
        $mine = sprintf(
            'vcr-am-sdk-php/%s (+https://github.com/blob-am/vcr-am-sdk-php)',
            $this->sdkVersion,
        );

        return $this->integration === null ? $mine : $mine . ' ' . $this->integration;
    }

    /**
     * @param non-empty-string                      $method
     * @param non-empty-string                      $path
     * @param array<string, mixed>|list<mixed>|null $jsonBody
     * @param ?array<string, string>                $query
     */
    private function buildRequest(
        string $method,
        string $path,
        ?array $jsonBody,
        ?array $query = null,
        ?string $idempotencyKey = null,
    ): RequestInterface {
        $url = $this->baseUrl . $path;

        if ($query !== null && $query !== []) {
            // Internal paths never carry their own query string, so unconditionally
            // prepend `?`. RFC 3986 encoding makes UTF-8 query values (e.g.
            // Armenian) round-trip cleanly through the API.
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent());

        if ($this->apiKey !== null) {
            $request = $request->withHeader('X-API-Key', $this->apiKey);
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

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
}
