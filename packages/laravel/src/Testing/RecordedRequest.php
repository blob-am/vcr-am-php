<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Testing;

use Psr\Http\Message\RequestInterface;

/**
 * Immutable snapshot of one request that flowed through {@see FakeHttpClient}.
 *
 * Holds the raw PSR-7 request plus a decoded body when the SDK sent JSON, so
 * test assertions can match on the parsed payload without redoing the
 * `json_decode` dance every time.
 */
final readonly class RecordedRequest
{
    /**
     * @param array<array-key, mixed>|null $decodedBody The JSON-decoded request
     *                                                  body when `Content-Type`
     *                                                  was `application/json`,
     *                                                  otherwise `null`. Bodies
     *                                                  that fail to decode also
     *                                                  fall back to `null`.
     */
    public function __construct(
        public RequestInterface $request,
        public string $method,
        public string $path,
        public string $rawBody,
        public ?array $decodedBody,
    ) {
    }
}
