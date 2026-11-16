<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Diagnostics;

use DateTimeImmutable;

/**
 * One HTTP call to the VCR.AM API, as it ended.
 *
 * Holds what a journal needs to answer "did the request leave, and what came
 * back" and nothing that would make the journal dangerous to keep: no headers,
 * no bodies, no query string. The path is the route only, so a customer
 * reference passed as a query parameter does not end up in it.
 */
final readonly class RequestAttempt
{
    /**
     * Response header carrying the server's id for the call.
     */
    public const TRACE_ID_HEADER = 'VCR-Trace-Id';

    public function __construct(
        public DateTimeImmutable $startedAt,
        public string $method,
        /** Path relative to the client's base URL, without the query string. */
        public string $path,
        public int $durationMs,
        /** Null when no response arrived: DNS, connect, TLS, timeout. */
        public ?int $statusCode,
        /**
         * The server's id for this call. Quote it to support, or pass it in
         * {@see \BlobSolutions\VcrAm\Input\SupportReportInput::$traceIds}, and
         * the matching entry of the server's request log is found directly.
         * Null when no response arrived or a proxy answered instead of the API.
         */
        public ?string $traceId,
        /** The `Idempotency-Key` the call carried, if any. */
        public ?string $idempotencyKey,
        /**
         * Why the call failed, in one line: the API's own `error` text for a
         * 4xx or 5xx, the transport's message when no response arrived, the
         * mapping complaint when a 2xx body did not fit. Null on success.
         */
        public ?string $failure,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->failure === null;
    }
}
