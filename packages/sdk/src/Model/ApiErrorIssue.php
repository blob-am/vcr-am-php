<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * One field-level complaint from a request that failed schema validation,
 * as carried in the API's error envelope under `issues`.
 *
 * `path` walks the request body to the offending field: `['items', 0, 'price']`
 * means the first item's price. Segments are strings for object keys and ints
 * for array indices, exactly as the server emits them.
 */
final readonly class ApiErrorIssue
{
    /**
     * @param list<string|int> $path
     */
    public function __construct(
        public array $path,
        public string $message,
        public string $code,
    ) {
    }

    /** Dotted rendering of {@see $path}, e.g. `items.0.price`. */
    public function pointer(): string
    {
        return implode('.', array_map(strval(...), $this->path));
    }
}
