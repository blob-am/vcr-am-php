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
