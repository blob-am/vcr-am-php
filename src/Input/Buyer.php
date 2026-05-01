<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\BuyerType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Buyer side of a sale receipt — either an individual (no fiscal id) or a
 * business entity (TIN required). Construct via the {@see self::individual()}
 * or {@see self::businessEntity()} factories so invalid combinations are
 * unrepresentable.
 */
final readonly class Buyer implements JsonSerializable
{
    private function __construct(
        public BuyerType $type,
        public ?string $tin,
        public ?SendReceiptToBuyer $receipt,
    ) {
    }

    public static function individual(?SendReceiptToBuyer $receipt = null): self
    {
        return new self(BuyerType::Individual, null, $receipt);
    }

    public static function businessEntity(string $tin, ?SendReceiptToBuyer $receipt = null): self
    {
        if (trim($tin) === '') {
            throw new InvalidArgumentException('TIN must not be empty for a business entity.');
        }

        return new self(BuyerType::BusinessEntity, $tin, $receipt);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = ['type' => $this->type->value];

        if ($this->tin !== null) {
            $payload['tin'] = $this->tin;
        }

        if ($this->receipt !== null) {
            $payload['receipt'] = $this->receipt;
        }

        return $payload;
    }
}
