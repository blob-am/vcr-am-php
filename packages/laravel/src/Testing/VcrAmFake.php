<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Testing;

use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Test double that rebinds the package's `VcrClient` (and its sandbox
 * sibling, when present) onto a {@see FakeHttpClient} backbone. Constructed
 * by {@see \BlobSolutions\LaravelVcrAm\Facades\VcrAm::fake()}; tests usually
 * never reference this class directly.
 *
 * Recording is shared between the production and sandbox channels — both
 * route through the same `FakeHttpClient`, which means assertions like
 * `assertSent('POST /sales')` fire regardless of which client made the call.
 * That matches the principle that tests care about "what wire payloads did
 * the system emit", not "which container slot did it come from".
 */
final class VcrAmFake
{
    private readonly FakeHttpClient $http;

    /**
     * @param array<string, Closure(RequestInterface): ResponseInterface|array<array-key, mixed>> $initialStubs
     */
    public function __construct(
        private readonly Application $app,
        array $initialStubs = [],
    ) {
        $this->http = new FakeHttpClient();

        foreach ($initialStubs as $matcher => $stub) {
            if ($matcher === '') {
                continue;
            }

            $this->stub($matcher, $stub);
        }

        $http = $this->http;

        $this->app->instance(VcrClient::class, new VcrClient(
            apiKey: 'fake-key',
            httpClient: $http,
        ));

        $this->app->instance(VcrAmServiceProvider::SANDBOX_BINDING, new VcrClient(
            apiKey: 'fake-sandbox-key',
            httpClient: $http,
        ));
    }

    /**
     * Register a stub for `"METHOD /path"`. `$stub` may be:
     *   - a `Closure` returning a PSR-7 `ResponseInterface`
     *   - a `Closure` returning an array (turned into a 200 JSON response)
     *   - a plain array (turned into a 200 JSON response unconditionally)
     *
     * @param non-empty-string $matcher
     * @param Closure(RequestInterface): ResponseInterface|Closure(RequestInterface): array<array-key, mixed>|array<array-key, mixed> $stub
     */
    public function stub(string $matcher, Closure|array $stub): self
    {
        $factory = $stub instanceof Closure
            ? static function (RequestInterface $request) use ($stub): ResponseInterface {
                $value = $stub($request);

                return $value instanceof ResponseInterface
                    ? $value
                    : FakeHttpClient::jsonResponse($value);
            }
        : static fn (): ResponseInterface => FakeHttpClient::jsonResponse($stub);

        $this->http->stub($matcher, $factory);

        return $this;
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->http->recorded();
    }

    /**
     * Assert at least one recorded request matches `"METHOD /path"`. When
     * `$bodyMatcher` is supplied, it receives the decoded JSON body of each
     * candidate request and must return `true` for at least one of them.
     *
     * @param non-empty-string $matcher
     * @param ?Closure(array<array-key, mixed>|null, RecordedRequest): bool $bodyMatcher
     */
    public function assertSent(string $matcher, ?Closure $bodyMatcher = null): self
    {
        $matches = $this->findMatches($matcher, $bodyMatcher);

        Assert::assertNotEmpty(
            $matches,
            sprintf(
                'Expected at least one request matching `%s`%s, but %s.',
                $matcher,
                $bodyMatcher !== null ? ' with the given body matcher' : '',
                $this->recorded() === []
                    ? 'no requests were recorded'
                    : 'only saw: ' . $this->renderRecordedSummary(),
            ),
        );

        return $this;
    }

    /**
     * Assert no request matches `"METHOD /path"`.
     *
     * @param non-empty-string $matcher
     * @param ?Closure(array<array-key, mixed>|null, RecordedRequest): bool $bodyMatcher
     */
    public function assertNotSent(string $matcher, ?Closure $bodyMatcher = null): self
    {
        $matches = $this->findMatches($matcher, $bodyMatcher);

        Assert::assertEmpty(
            $matches,
            sprintf(
                'Expected no requests matching `%s`%s, but found %d.',
                $matcher,
                $bodyMatcher !== null ? ' with the given body matcher' : '',
                count($matches),
            ),
        );

        return $this;
    }

    /**
     * Assert nothing was sent through the SDK at all.
     */
    public function assertNothingSent(): self
    {
        Assert::assertEmpty(
            $this->recorded(),
            'Expected no SDK requests, but saw: ' . $this->renderRecordedSummary(),
        );

        return $this;
    }

    /**
     * Assert exactly `$count` requests were sent through the SDK.
     */
    public function assertSentCount(int $count): self
    {
        Assert::assertCount(
            $count,
            $this->recorded(),
            sprintf(
                'Expected %d SDK request(s), got %d. Recorded: %s',
                $count,
                count($this->recorded()),
                $this->renderRecordedSummary(),
            ),
        );

        return $this;
    }

    /**
     * Drop all recorded requests and stubs. Useful in between phases of a
     * single test that wants to assert on a clean window.
     */
    public function reset(): self
    {
        $this->http->reset();

        return $this;
    }

    /**
     * @param non-empty-string $matcher
     * @param ?Closure(array<array-key, mixed>|null, RecordedRequest): bool $bodyMatcher
     *
     * @return list<RecordedRequest>
     */
    private function findMatches(string $matcher, ?Closure $bodyMatcher): array
    {
        [$method, $path] = $this->splitMatcher($matcher);

        $matches = [];
        foreach ($this->recorded() as $entry) {
            if ($entry->method !== $method || $entry->path !== $path) {
                continue;
            }

            if ($bodyMatcher !== null && ! $bodyMatcher($entry->decodedBody, $entry)) {
                continue;
            }

            $matches[] = $entry;
        }

        return $matches;
    }

    /**
     * @param non-empty-string $matcher
     *
     * @return array{0: string, 1: string}
     */
    private function splitMatcher(string $matcher): array
    {
        $trimmed = trim($matcher);
        if (preg_match('/^([A-Za-z]+)\s+(\/\S*)$/', $trimmed, $match) !== 1) {
            Assert::fail(sprintf(
                'VcrAm fake: assertion matcher `%s` must be in the form "METHOD /path".',
                $matcher,
            ));
        }

        return [strtoupper($match[1]), $match[2]];
    }

    private function renderRecordedSummary(): string
    {
        if ($this->recorded() === []) {
            return '(none)';
        }

        return implode(', ', array_map(
            static fn (RecordedRequest $r) => sprintf('%s %s', $r->method, $r->path),
            $this->recorded(),
        ));
    }
}
