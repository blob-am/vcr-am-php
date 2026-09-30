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
 *
 * `rawBody` is the body it could not map, with credential-bearing fields
 * replaced: a 200 from `/connect/exchange` whose shape has drifted would
 * otherwise carry a minted API key into whatever logs this exception.
 */
final class VcrValidationException extends VcrException
{
    public function __construct(
        public readonly string $rawBody,
        /**
         * The request as sent, minus its secrets — see
         * {@see VcrApiException::$request}.
         */
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
