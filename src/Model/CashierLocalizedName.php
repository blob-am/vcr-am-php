<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\Language;

/**
 * A single language-specific localisation of a cashier's display name.
 *
 * @see CashierListItem::$name
 */
final readonly class CashierLocalizedName
{
    public function __construct(
        public Language $language,
        public string $content,
    ) {
    }
}
