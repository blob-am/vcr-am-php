<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\Language;

/**
 * One localised content entry — used for cashier names, department titles,
 * and offer titles in detail responses. The API returns localisation as a
 * list of entries (rather than a per-language map) because each entry has
 * its own database id.
 */
final readonly class LocalizationEntry
{
    public function __construct(
        public int $id,
        public Language $language,
        public string $content,
    ) {
    }
}
