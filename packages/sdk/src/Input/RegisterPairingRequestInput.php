<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\PairingClient::registerRequest()}.
 *
 * `redirectUri` is shown to the merchant on the approval screen, so it has to
 * be a page they would recognise as their own shop — an absolute https URL
 * (plain http only on loopback), with no fragment and no embedded credentials.
 *
 * `state` is the store's own nonce, returned untouched on the redirect. It is
 * what lets the store tell its own pairing from a replayed or forged one; the
 * SDK does not generate it, because it has to be checked against something the
 * store persisted.
 *
 * `codeChallenge` is {@see CodeVerifier::challengeFor()} of a verifier the
 * store keeps to itself until the exchange.
 */
final readonly class RegisterPairingRequestInput implements JsonSerializable
{
    public function __construct(
        public string $redirectUri,
        public string $storeName,
        public string $state,
        public string $codeChallenge,
    ) {
        if (trim($redirectUri) === '') {
            throw new InvalidArgumentException('redirectUri must not be empty.');
        }

        if (trim($storeName) === '') {
            throw new InvalidArgumentException('storeName must not be empty.');
        }

        if (trim($state) === '') {
            throw new InvalidArgumentException('state must not be empty.');
        }

        if (trim($codeChallenge) === '') {
            throw new InvalidArgumentException('codeChallenge must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'redirectUri' => $this->redirectUri,
            'storeName' => $this->storeName,
            'state' => $this->state,
            'codeChallenge' => $this->codeChallenge,
        ];
    }
}
