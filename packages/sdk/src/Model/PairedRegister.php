<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * The register a merchant paired, and the key for it. Returned by
 * {@see \BlobSolutions\VcrAm\PairingClient::exchangeCode()}.
 *
 * `apiKey` is shown exactly once and is not retrievable afterwards — persist
 * it before doing anything else with this object, and never log it. The
 * remaining fields exist so the store can show the merchant which register it
 * ended up bound to; `crn` is null for a register that has not completed
 * activation with the tax service.
 */
final readonly class PairedRegister
{
    public function __construct(
        public string $apiKey,
        public string $expiresAt,
        public int $vcrId,
        public ?string $crn,
        public string $registerName,
    ) {
    }
}
