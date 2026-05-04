<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\RefundReason;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Top-level argument shape for {@see \BlobSolutions\VcrAm\VcrClient::registerSaleRefund()}.
 *
 * Mirrors `RegisterSaleRefundInput` from the TypeScript SDK. A minimal full
 * refund needs only `cashier` and `saleId` — omit `items` to refund the
 * whole sale, or pass a partial-refund list of {@see RefundItemInput}.
 */
final readonly class RegisterSaleRefundInput implements JsonSerializable
{
    /**
     * @param ?list<RefundItemInput> $items Empty list is rejected — pass
     *                                      `null` to refund the entire sale.
     */
    public function __construct(
        public CashierId $cashier,
        public int $saleId,
        public ?SendReceiptToBuyer $receipt = null,
        public ?RefundReason $reason = null,
        public ?string $reasonNote = null,
        public ?RefundAmount $refundAmounts = null,
        public ?array $items = null,
    ) {
        if ($saleId < 0) {
            throw new InvalidArgumentException('saleId must be non-negative.');
        }

        if ($reasonNote !== null && trim($reasonNote) === '') {
            throw new InvalidArgumentException('reasonNote must not be empty when provided.');
        }

        if ($items !== null && $items === []) {
            throw new InvalidArgumentException('items must contain at least one entry when provided. Pass null for a full refund.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'cashier' => $this->cashier,
            'saleId' => $this->saleId,
        ];

        if ($this->receipt !== null) {
            $payload['receipt'] = $this->receipt;
        }

        if ($this->reason !== null) {
            $payload['reason'] = $this->reason->value;
        }

        if ($this->reasonNote !== null) {
            $payload['reasonNote'] = $this->reasonNote;
        }

        if ($this->refundAmounts !== null) {
            $payload['refundAmounts'] = $this->refundAmounts;
        }

        if ($this->items !== null) {
            $payload['items'] = $this->items;
        }

        return $payload;
    }
}
