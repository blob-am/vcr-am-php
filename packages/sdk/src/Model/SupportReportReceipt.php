<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::submitSupportReport()}.
 *
 * Show `reference` to the person who sent the report. Quoting it to support
 * is how their message is matched to the diagnostics.
 */
final readonly class SupportReportReceipt
{
    public function __construct(
        public string $reference,
        /** ISO 8601, UTC. */
        public string $createdAt,
    ) {
    }
}
