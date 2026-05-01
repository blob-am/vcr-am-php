<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * One line of a partial refund — references a sale item by its server-side
 * `srcId` and refunds `quantity` units.
 *
 * `emarks` carries identifiers of consumed excise marks (alcohol, tobacco,
 * pharmaceuticals) that should be released back to the registry on refund.
 * Omit the field for non-marked goods.
 */
final readonly class RefundItemInput implements JsonSerializable
{
    /**
     * @param ?list<string> $emarks
     */
    public function __construct(
        public int $srcId,
        public string $quantity,
        public ?array $emarks = null,
    ) {
        if ($srcId < 0) {
            throw new InvalidArgumentException('srcId must be non-negative.');
        }

        if (trim($quantity) === '') {
            throw new InvalidArgumentException('quantity must not be empty.');
        }

        if ($emarks !== null) {
            foreach ($emarks as $emark) {
                if (trim($emark) === '') {
                    throw new InvalidArgumentException('emarks entries must not be empty.');
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'srcId' => $this->srcId,
            'quantity' => $this->quantity,
        ];

        if ($this->emarks !== null) {
            $payload['emarks'] = $this->emarks;
        }

        return $payload;
    }
}
