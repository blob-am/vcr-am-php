<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

use BlobSolutions\VcrAm\TaxRegime;

/**
 * One entry in the result of {@see \BlobSolutions\VcrAm\VcrClient::listDepartments()}.
 *
 * Only departments confirmed by the tax service are returned, so
 * `internalId` is safe to reference from a sale item's `department.id`.
 * `title` is keyed by language code (e.g. `hy`, `ru`, `en`); legacy
 * departments created before titles were mandatory may surface an empty
 * map.
 */
final readonly class DepartmentListItem
{
    /**
     * @param array<string, DepartmentLocalizedTitle> $title
     */
    public function __construct(
        public int $internalId,
        public ?string $externalId,
        public TaxRegime $taxRegime,
        public array $title,
    ) {
    }
}
