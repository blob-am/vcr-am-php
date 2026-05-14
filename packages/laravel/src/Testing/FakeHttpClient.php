<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Testing;

use Closure;
use JsonException;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Drop-in PSR-18 client for tests. Holds a set of stubbed responses keyed by
 * `"METHOD /path"` (case-insensitive method, exact path), records every
 * request that flows through, and lets callers pop the recorded calls back
 * out for assertions.
 *
 * Unknown requests fail loudly with a {@see RuntimeException} that names the
 * matcher key the test would need to register. The alternative — silently
 * returning `200 {}` — masks bugs in production code that adds new SDK calls
 * the tests aren't watching for.
 *
 * Pattern matching is exact-only by design. Wildcards mostly mask typos in
 * URLs; the cost of declaring one stub per endpoint is small and worth the
 * extra clarity in test output.
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var array<string, Closure(RequestInterface): ResponseInterface>
     */
    private array $stubs = [];

    /**
     * @var list<RecordedRequest>
     */
    private array $recorded = [];

    /**
     * Register a stubbed response for `"METHOD /path"`. The factory receives
     * the captured request so stubs can vary their output on inspection
     * (e.g. echo back the input cashier id).
     *
     * @param non-empty-string                          $matcher One of `"POST /sales"`,
     *                                                           `"GET /cashiers"`, etc.
     *                                                           Method is upper-cased
     *                                                           internally for
     *                                                           case-insensitive matching.
     * @param Closure(RequestInterface): ResponseInterface $factory
     */
    public function stub(string $matcher, Closure $factory): void
    {
        $this->stubs[self::normaliseMatcher($matcher)] = $factory;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        // The SDK's base URL prefixes its path on every request (e.g.
        // `https://vcr.am/api/v1/sales`). For test ergonomics we strip the
        // known SDK base path so stubs and assertions can use the bare
        // endpoint name that callers read in the SDK source (`POST /sales`).
        $barePath = self::stripBaseFromPath($request->getUri()->getPath());
        $bareKey = $method . ' ' . $barePath;

        $factory = $this->stubs[$bareKey] ?? null;

        $rawBody = (string) $request->getBody();
        $request->getBody()->rewind();

        $this->recorded[] = new RecordedRequest(
            request: $request,
            method: $method,
            path: $barePath,
            rawBody: $rawBody,
            decodedBody: self::tryDecodeJson($rawBody),
        );

        if ($factory === null) {
            throw new RuntimeException(sprintf(
                'VcrAm fake: no stub registered for `%s`. Call VcrAm::fake([\'%s\' => ...]) '
                . 'or VcrAm::fake()->stub(\'%s\', ...) before exercising this code path.',
                $bareKey,
                $bareKey,
                $bareKey,
            ));
        }

        return $factory($request);
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function reset(): void
    {
        $this->stubs = [];
        $this->recorded = [];
    }

    /**
     * @param non-empty-string $matcher
     *
     * @return non-empty-string
     */
    private static function normaliseMatcher(string $matcher): string
    {
        $trimmed = trim($matcher);
        if (preg_match('/^([A-Za-z]+)\s+(\/\S*)$/', $trimmed, $match) !== 1) {
            throw new RuntimeException(sprintf(
                'VcrAm fake: matcher `%s` is not in the form "METHOD /path" (e.g. "POST /sales").',
                $matcher,
            ));
        }

        return strtoupper($match[1]) . ' ' . $match[2];
    }

    /**
     * Strip the SDK's known base path so recorded `path` and stub matchers
     * stay readable. A request to `https://vcr.am/api/v1/sales` records as
     * `/sales`; a request to a non-`/api/v1` base URL (e.g. when
     * `VCR_AM_BASE_URL` is overridden) records its path verbatim.
     */
    private const SDK_BASE_PATH = '/api/v1';

    private static function stripBaseFromPath(string $path): string
    {
        return str_starts_with($path, self::SDK_BASE_PATH)
            ? substr($path, strlen(self::SDK_BASE_PATH))
            : $path;
    }

    /**
     * Decode the JSON-shaped request bodies that the SDK emits, so assertions
     * can match on the parsed shape without the test re-doing `json_decode`.
     *
     * Returns `null` for: empty body (e.g. GET), body that doesn't parse as
     * JSON, body whose JSON root is a scalar. PSR-18 doesn't constrain the
     * body shape, so the fake guards against all three rather than crashing
     * a recorder — the test should still be able to inspect `rawBody` even
     * when JSON parsing failed.
     *
     * @return array<array-key, mixed>|null
     */
    private static function tryDecodeJson(string $body): ?array
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Helper to construct a 200 JSON response from a value the SDK can map.
     *
     * @param array<array-key, mixed> $payload
     */
    public static function jsonResponse(array $payload, int $status = 200): ResponseInterface
    {
        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
