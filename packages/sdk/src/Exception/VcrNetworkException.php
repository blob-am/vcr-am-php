<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Exception;

use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Thrown when a request fails at the network/transport layer — DNS failure,
 * TCP reset, TLS handshake error, or timeout — before any HTTP response is
 * received. The PSR-18 client exception is preserved as the cause.
 */
final class VcrNetworkException extends VcrException
{
    public function __construct(
        /**
         * The request as sent, minus its secrets: the `X-API-Key` header is
         * removed and credential-bearing body fields are replaced with
         * `[REDACTED]`, because APMs serialise exception state. Read it to see
         * what was attempted — do not replay it, since what it now holds is not
         * what the server would accept.
         */
        public readonly RequestInterface $request,
        Throwable $previous,
    ) {
        parent::__construct(
            'VCR.AM API request failed at the network/transport layer: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }
}
