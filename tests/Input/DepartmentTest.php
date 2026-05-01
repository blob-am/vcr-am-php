<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\Department;

it('serializes the id', function (): void {
    expect((new Department(0))->jsonSerialize())->toBe(['id' => 0])
        ->and((new Department(42))->jsonSerialize())->toBe(['id' => 42]);
});

it('rejects a negative id', function (): void {
    new Department(-1);
})->throws(InvalidArgumentException::class, 'Department id must be non-negative.');
