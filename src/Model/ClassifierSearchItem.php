<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * One entry in the result of
 * {@see \BlobSolutions\VcrAm\VcrClient::searchClassifier()}.
 *
 * `title` is nullable: the API may emit `{ code }` without a title when the
 * classifier entry exists in the lookup index but lacks a translation in
 * the requested language. UI code should fall back to displaying just the
 * code in that case.
 */
final readonly class ClassifierSearchItem
{
    public function __construct(
        public string $code,
        public ?string $title = null,
    ) {
    }
}
