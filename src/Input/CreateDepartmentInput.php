<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\TaxRegime;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::createDepartment()}.
 *
 * `externalId` is optional — pass it to bind the department to a record
 * in your own system (POS, ERP) for later reconciliation.
 */
final readonly class CreateDepartmentInput implements JsonSerializable
{
    public function __construct(
        public TaxRegime $taxRegime,
        public ?string $externalId = null,
    ) {
        if ($externalId !== null && trim($externalId) === '') {
            throw new InvalidArgumentException('externalId must not be empty when provided.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        $payload = ['taxRegime' => $this->taxRegime->value];

        if ($this->externalId !== null) {
            $payload['externalId'] = $this->externalId;
        }

        return $payload;
    }
}
