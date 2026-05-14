<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\VcrMode;

/**
 * Identity of the VCR (Virtual Cash Register) that the calling API key belongs to.
 *
 * Returned by {@see \BlobSolutions\VcrAm\VcrClient::whoami()}. Use it to tell
 * production-vs-sandbox keys apart in client-side diagnostics, log lines, and
 * health-check output.
 *
 * `$crn` is `null` until the VCR is activated with the State Revenue Committee —
 * a just-imported VCR is reachable via the API but does not yet have a CRN.
 */
final readonly class AccountInfo
{
    public function __construct(
        public int $vcrId,
        public ?string $crn,
        public VcrMode $mode,
        public string $tradingPlatformName,
        public AccountBusinessEntity $businessEntity,
    ) {
    }
}
