<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thrown when the VCR.AM API returns a non-2xx HTTP response. The original
 * request and response are preserved verbatim so callers can inspect headers,
 * trace IDs, or replay the call against a different environment.
 */
final class VcrApiException extends VcrException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $apiErrorCode,
        public readonly ?string $apiErrorMessage,
        public readonly string $rawBody,
        public readonly RequestInterface $request,
        public readonly ResponseInterface $response,
        ?Throwable $previous = null,
    ) {
        $detail = '';

        if ($apiErrorCode !== null) {
            $detail .= ' [' . $apiErrorCode . ']';
        }

        if ($apiErrorMessage !== null) {
            $detail .= ': ' . $apiErrorMessage;
        }

        parent::__construct(
            sprintf('VCR.AM API returned HTTP %d%s', $statusCode, $detail),
            0,
            $previous,
        );
    }
}
