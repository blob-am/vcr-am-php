<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::createDepartment()}.
 *
 * `department` is the new department's internal numeric id; `message` is a
 * human-readable confirmation string from the API (e.g. for logging).
 */
final readonly class CreateDepartmentResponse
{
    public function __construct(
        public string $message,
        public int $department,
    ) {
    }
}
