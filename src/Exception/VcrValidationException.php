<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Thrown when the VCR.AM API responds successfully but the response payload
 * does not match the expected schema. This indicates either a wire-format
 * change on the server or a corrupted payload — never a caller-induced error.
 */
final class VcrValidationException extends VcrException
{
    public function __construct(
        public readonly string $rawBody,
        public readonly RequestInterface $request,
        public readonly ?ResponseInterface $response,
        public readonly string $detail,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'VCR.AM API response did not match the expected schema: ' . $detail,
            0,
            $previous,
        );
    }
}
