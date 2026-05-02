<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::createOffer()}.
 *
 * Distinct from {@see Offer} (which is the inline offer reference embedded
 * inside a {@see SaleItem}): this is the top-level "create a brand-new
 * offer record on the account" payload. The shapes overlap but their
 * domain semantics differ — keep them separate, mirroring the TypeScript
 * SDK's `CreateOfferInput` vs `Offer` split.
 *
 * Title is always localised (no universal flavour) — pass {@see LocalizedName}
 * with the per-language map and a translation/transliteration strategy.
 */
final readonly class CreateOfferInput implements JsonSerializable
{
    public function __construct(
        public OfferType $type,
        public string $classifierCode,
        public LocalizedName $title,
        public Unit $defaultMeasureUnit,
        public Department $defaultDepartment,
        public ?string $externalId = null,
    ) {
        if (trim($classifierCode) === '') {
            throw new InvalidArgumentException('classifierCode must not be empty.');
        }

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
            'type' => $this->type->value,
            'classifierCode' => $this->classifierCode,
            'title' => $this->title,
            'defaultMeasureUnit' => $this->defaultMeasureUnit->value,
            'defaultDepartment' => $this->defaultDepartment,
        ];

        if ($this->externalId !== null) {
            $payload['externalId'] = $this->externalId;
        }

        return $payload;
    }
}
