<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Top-level argument shape for {@see \BlobSolutions\VcrAm\VcrClient::registerSale()}.
 *
 * Mirrors the wire format produced by the TypeScript SDK so payloads sent
 * from PHP and Node hit the API identically.
 */
final readonly class RegisterSaleInput implements JsonSerializable
{
    /**
     * @param list<SaleItem> $items Must contain at least one item; an empty
     *                              list is rejected at runtime (defence in
     *                              depth — callers may construct inputs from
     *                              decoded JSON / config where the type
     *                              system can't enforce non-emptiness).
     */
    public function __construct(
        public CashierId $cashier,
        public array $items,
        public SaleAmount $amount,
        public Buyer $buyer,
    ) {
        if ($items === []) {
            throw new InvalidArgumentException('A sale must contain at least one item.');
        }
    }

    /**
     * @return array{cashier: CashierId, items: list<SaleItem>, amount: SaleAmount, buyer: Buyer}
     */
    public function jsonSerialize(): array
    {
        return [
            'cashier' => $this->cashier,
            'items' => $this->items,
            'amount' => $this->amount,
            'buyer' => $this->buyer,
        ];
    }
}
