<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\Language;

/**
 * A single language-specific localisation of a department's display title.
 *
 * @see DepartmentListItem::$title
 */
final readonly class DepartmentLocalizedTitle
{
    public function __construct(
        public Language $language,
        public string $content,
    ) {
    }
}
