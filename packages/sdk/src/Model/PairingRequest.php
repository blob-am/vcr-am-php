<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * A registered, not-yet-approved pairing. Returned by
 * {@see \BlobSolutions\VcrAm\PairingClient::registerRequest()}.
 *
 * Nothing has been granted at this point. Send the merchant's browser to
 * `connectUrl`; if they approve, they return to the store's own `redirectUri`
 * with `code` and the `state` that was registered, and that code is what
 * {@see \BlobSolutions\VcrAm\PairingClient::exchangeCode()} turns into a key.
 *
 * `expiresAt` is an ISO-8601 instant, kept as a string for the same reason
 * every other date in this SDK is: the API's format is the contract, and
 * parsing it is the caller's choice.
 */
final readonly class PairingRequest
{
    public function __construct(
        public string $requestId,
        public string $connectUrl,
        public string $expiresAt,
    ) {
    }
}
