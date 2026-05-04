<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Reference to an offer (product or service) sold in a sale item. Either
 * point at an existing offer by its `externalId` ({@see self::existing()}),
 * or describe a brand-new offer that the API should auto-create on this
 * call ({@see self::createNew()}).
 *
 * Both shapes carry an `externalId`; the discriminator is the presence of
 * the new-offer fields (title, classifierCode, defaultMeasureUnit,
 * defaultDepartment, type).
 */
final readonly class Offer implements JsonSerializable
{
    private function __construct(
        public string $externalId,
        public ?OfferTitle $title,
        public ?OfferType $type,
        public ?string $classifierCode,
        public ?Unit $defaultMeasureUnit,
        public ?Department $defaultDepartment,
    ) {
    }

    public static function existing(string $externalId): self
    {
        if (trim($externalId) === '') {
            throw new InvalidArgumentException('externalId must not be empty.');
        }

        return new self($externalId, null, null, null, null, null);
    }

    public static function createNew(
        string $externalId,
        OfferTitle $title,
        OfferType $type,
        string $classifierCode,
        Unit $defaultMeasureUnit,
        Department $defaultDepartment,
    ): self {
        if (trim($externalId) === '') {
            throw new InvalidArgumentException('externalId must not be empty.');
        }

        if (trim($classifierCode) === '') {
            throw new InvalidArgumentException('classifierCode must not be empty.');
        }

        return new self(
            $externalId,
            $title,
            $type,
            $classifierCode,
            $defaultMeasureUnit,
            $defaultDepartment,
        );
    }

    public function isNew(): bool
    {
        return $this->title !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        if (! $this->isNew()) {
            return ['externalId' => $this->externalId];
        }

        assert($this->title !== null);
        assert($this->type !== null);
        assert($this->classifierCode !== null);
        assert($this->defaultMeasureUnit !== null);
        assert($this->defaultDepartment !== null);

        return [
            'title' => $this->title,
            'type' => $this->type->value,
            'classifierCode' => $this->classifierCode,
            'defaultMeasureUnit' => $this->defaultMeasureUnit->value,
            'defaultDepartment' => $this->defaultDepartment,
            'externalId' => $this->externalId,
        ];
    }
}
