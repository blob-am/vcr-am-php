<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Slice of the business entity that owns the calling VCR — just enough fields
 * to render a useful "you are talking to X" line in CLIs and logs.
 *
 * `$name` is the English form of the entity name (the canonical
 * machine-readable fallback in the VCR.AM localisation cascade) and may be the
 * empty string if no English name is registered.
 */
final readonly class AccountBusinessEntity
{
    public function __construct(
        public string $tin,
        public string $name,
    ) {
    }
}
