<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use JsonSerializable;

/**
 * Top-level argument shape for {@see \BlobSolutions\VcrAm\VcrClient::registerPrepayment()}.
 *
 * Mirrors `RegisterPrepaymentInput` from the TypeScript SDK. Unlike a sale,
 * a prepayment does not carry items — it represents an advance payment
 * that will be redeemed against a future sale.
 */
final readonly class RegisterPrepaymentInput implements JsonSerializable
{
    public function __construct(
        public CashierId $cashier,
        public PrepaymentAmount $amount,
        public Buyer $buyer,
    ) {
    }

    /**
     * @return array{cashier: CashierId, amount: PrepaymentAmount, buyer: Buyer}
     */
    public function jsonSerialize(): array
    {
        return [
            'cashier' => $this->cashier,
            'amount' => $this->amount,
            'buyer' => $this->buyer,
        ];
    }
}
