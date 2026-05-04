<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\RefundReason;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Top-level argument shape for {@see \BlobSolutions\VcrAm\VcrClient::registerPrepaymentRefund()}.
 *
 * Mirrors `RegisterPrepaymentRefundInput` from the TypeScript SDK. The
 * minimal payload is `cashier` + `prepaymentId` — the API refunds the
 * full prepayment by default. Provide `reason`, `reasonNote`, or `receipt`
 * to enrich the refund record.
 */
final readonly class RegisterPrepaymentRefundInput implements JsonSerializable
{
    public function __construct(
        public CashierId $cashier,
        public int $prepaymentId,
        public ?SendReceiptToBuyer $receipt = null,
        public ?RefundReason $reason = null,
        public ?string $reasonNote = null,
    ) {
        if ($prepaymentId < 0) {
            throw new InvalidArgumentException('prepaymentId must be non-negative.');
        }

        if ($reasonNote !== null && trim($reasonNote) === '') {
            throw new InvalidArgumentException('reasonNote must not be empty when provided.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'cashier' => $this->cashier,
            'prepaymentId' => $this->prepaymentId,
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

        return $payload;
    }
}
