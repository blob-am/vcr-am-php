<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Exception;

use BlobSolutions\VcrAm\Model\ApiErrorIssue;
use BlobSolutions\VcrAm\Model\PendingResource;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thrown when the VCR.AM API returns a non-2xx HTTP response. The original
 * request and response are preserved verbatim so callers can inspect headers,
 * trace IDs, or replay the call against a different environment.
 *
 * The API's error envelope is `{ error, issues?, requestId?, pending? }`;
 * every field of it is surfaced here. `rawBody` remains available for the
 * cases the envelope does not cover — a proxy's HTML error page, say.
 */
final class VcrApiException extends VcrException
{
    /**
     * @param list<ApiErrorIssue> $issues
     */
    public function __construct(
        public readonly int $statusCode,
        /** The envelope's `error`; null when the body was not a VCR envelope. */
        public readonly ?string $apiErrorMessage,
        public readonly string $rawBody,
        public readonly RequestInterface $request,
        public readonly ResponseInterface $response,
        /** Field-level complaints, present when the request failed validation. */
        public readonly array $issues = [],
        /**
         * Correlation ID on unexpected 5xx responses. Quote it to support and
         * the matching log entry and Sentry event can be found.
         */
        public readonly ?string $requestId = null,
        /**
         * Set when the document survived despite the error — see
         * {@see PendingResource}. Do not resend the request when this is
         * present; read the document back instead.
         */
        public readonly ?PendingResource $pending = null,
        ?Throwable $previous = null,
    ) {
        $detail = $apiErrorMessage === null ? '' : ': ' . $apiErrorMessage;

        parent::__construct(
            sprintf('VCR.AM API returned HTTP %d%s', $statusCode, $detail),
            0,
            $previous,
        );
    }
}
