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

    private const REDACTED = '[REDACTED]';

    /**
     * Request-body fields that are credentials, by exact name.
     *
     * Exact and not by substring, which is what the server's own redactor uses:
     * here the list has to be narrow enough to keep `classifierCode` and
     * `errorCode` intact, because a request body on an exception is how a
     * rejected receipt gets diagnosed.
     *
     * `code` and `codeVerifier` are the two halves of a pairing exchange, and
     * `/connect/exchange` is the only body in this SDK carrying either name. A
     * network failure there is the case that matters: the code may still be
     * unspent, and the key it mints is register-wide for two years.
     *
     * `password` is the cashier PIN. {@see \BlobSolutions\VcrAm\Input\CreateCashierInput}
     * keeps it out of a `var_dump` through `__debugInfo()`, which does nothing
     * for the serialized body that travels on the exception.
     */
    private const SECRET_REQUEST_KEYS = ['code', 'codeVerifier', 'password', 'pin', 'apiKey'];

    /**
     * Response-body fields that are credentials, by exact name.
     *
     * Deliberately shorter than {@see SECRET_REQUEST_KEYS}: on the way back
     * `code` is an SRC error code — {@see ErrorEnvelope} reads exactly that key
     * — and hiding it would blind the one field a refused receipt is read from.
     *
     * `apiKey` is here for one response: a 200 from `/connect/exchange` whose
     * shape has drifted becomes a `VcrValidationException` carrying the body,
     * and that body is the only copy of a key that is never shown again.
     */
    private const SECRET_RESPONSE_KEYS = ['apiKey'];

    /**
     * Depth cap on the redaction walk. Nothing this SDK sends is close to it;
     * the cap exists so a pathological structure cannot turn a redaction into
     * an unbounded recursion.
     */
    private const MAX_REDACTION_DEPTH = 12;

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
                $this->redactResponseBody($rawBody),
                $this->redactRequest($request),
                $response,
                'response body is not valid JSON: ' . $e->getMessage(),
                $e,
            );
        }

        if (! is_array($decoded)) {
            throw new VcrValidationException(
                $this->redactResponseBody($rawBody),
                $this->redactRequest($request),
                $response,
                'expected JSON array or object at the response root, got ' . get_debug_type($decoded),
            );
        }

        try {
            return $this->mapper->map($signature, Source::array($decoded));
        } catch (MappingError $e) {
            throw new VcrValidationException(
                $this->redactResponseBody($rawBody),
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
     * Strips secrets from the request before it gets attached to a
     * public-facing exception. APMs and loggers that introspect exception state
     * (Sentry, Bugsnag, Laravel's verbose handler) routinely dump request
     * headers *and bodies* — we don't want a credential in those breadcrumbs.
     *
     * The header half has always been here. The body half arrived with
     * pairing: until then every body this SDK sent was business data, and the
     * one that was not — a cashier's PIN — was only protected against
     * `var_dump`.
     */
    private function redactRequest(RequestInterface $request): RequestInterface
    {
        $request = $request->withoutHeader('X-API-Key');

        $body = (string) $request->getBody();
        if ($body === '') {
            return $request;
        }

        $redacted = self::redactJson($body, self::SECRET_REQUEST_KEYS);
        if ($redacted === null) {
            // Not JSON, so not a body this SDK built. Nothing to walk, and
            // replacing it with a marker would lose what little it says.
            return $request;
        }

        return $request->withBody($this->streamFactory->createStream($redacted));
    }

    /**
     * The response body as it may be attached to an exception.
     *
     * Only the success path needs this: a mapping failure is the one way a body
     * that contains a minted API key reaches exception state.
     */
    private function redactResponseBody(string $rawBody): string
    {
        return self::redactJson($rawBody, self::SECRET_RESPONSE_KEYS) ?? $rawBody;
    }

    /**
     * Re-encoded JSON with every named key replaced, or null when the input was
     * not a JSON array or object to begin with.
     *
     * @param list<string> $secretKeys
     */
    private static function redactJson(string $raw, array $secretKeys): ?string
    {
        try {
            $decoded = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                return null;
            }

            $encoded = json_encode(
                self::redactValue($decoded, $secretKeys, 0),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return null;
        }

        return $encoded;
    }

    /**
     * @param list<string> $secretKeys
     */
    private static function redactValue(mixed $value, array $secretKeys, int $depth): mixed
    {
        if ($depth >= self::MAX_REDACTION_DEPTH) {
            return self::REDACTED;
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $entry) {
            $result[$key] = is_string($key) && in_array($key, $secretKeys, strict: true)
                ? self::REDACTED
                : self::redactValue($entry, $secretKeys, $depth + 1);
        }

        return $result;
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
