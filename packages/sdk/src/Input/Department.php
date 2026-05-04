<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Reference to a department by its internal numeric id.
 */
final readonly class Department implements JsonSerializable
{
    public function __construct(public int $id)
    {
        if ($id < 0) {
            throw new InvalidArgumentException('Department id must be non-negative.');
        }
    }

    /**
     * @return array{id: int}
     */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id];
    }
}
