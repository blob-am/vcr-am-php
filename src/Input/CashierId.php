<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Reference to a cashier — either by VCR.AM internal numeric id or by the
 * customer-defined deskId. The discriminator is the presence of one field
 * vs. the other on the wire (no `type` tag).
 *
 * Construct via the {@see self::byInternalId()} or {@see self::byDeskId()}
 * factories.
 */
final readonly class CashierId implements JsonSerializable
{
    private function __construct(
        public ?int $id,
        public ?string $deskId,
    ) {
    }

    public static function byInternalId(int $id): self
    {
        if ($id < 0) {
            throw new InvalidArgumentException('Cashier id must be non-negative.');
        }

        return new self($id, null);
    }

    public static function byDeskId(string $deskId): self
    {
        if (trim($deskId) === '') {
            throw new InvalidArgumentException('Cashier deskId must not be empty.');
        }

        return new self(null, $deskId);
    }

    /**
     * @return array{id: int}|array{deskId: string}
     */
    public function jsonSerialize(): array
    {
        if ($this->id !== null) {
            return ['id' => $this->id];
        }

        assert($this->deskId !== null);

        return ['deskId' => $this->deskId];
    }
}
