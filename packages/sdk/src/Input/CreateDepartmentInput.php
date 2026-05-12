<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\TaxRegime;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::createDepartment()}.
 *
 * `title` is required and follows the same shape used by
 * {@see CreateCashierInput::$name} and {@see CreateOfferInput::$title} —
 * Armenian (`hy`) content is mandatory; Russian/English are optional and
 * auto-derived at render time when missing.
 *
 * `externalId` is optional — pass it to bind the department to a record
 * in your own system (POS, ERP) for later reconciliation.
 */
final readonly class CreateDepartmentInput implements JsonSerializable
{
    public function __construct(
        public TaxRegime $taxRegime,
        public LocalizedName $title,
        public ?string $externalId = null,
    ) {
        if ($externalId !== null && trim($externalId) === '') {
            throw new InvalidArgumentException('externalId must not be empty when provided.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'taxRegime' => $this->taxRegime->value,
            'title' => $this->title,
        ];

        if ($this->externalId !== null) {
            $payload['externalId'] = $this->externalId;
        }

        return $payload;
    }
}
