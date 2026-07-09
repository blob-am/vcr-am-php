<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Unit;
use InvalidArgumentException;
use JsonSerializable;

/**
 * One line item in a sale receipt.
 *
 * Decimal fields (`quantity`, `price`, `totalAmountTolerance`) are passed as
 * strings to preserve precision over the wire — consistent with the
 * TypeScript SDK and the underlying Prisma `Decimal` type.
 *
 * `emarks` carries identifiers of excise marks consumed by this line item
 * (alcohol, tobacco, pharmaceuticals — Govt Decision 1976-N, effective
 * 2026-05-01). Per-item by domain design — the wire format flattens them
 * into a single top-level array per receipt at the VCR API boundary, but
 * the SDK preserves item-level grouping so refund flows can correctly
 * subset codes against the original sale. Omit the field for unmarked
 * goods.
 *
 * `currency` is an optional ISO 4217 input currency for foreign-currency
 * sales (HO-234-N). When set to a non-AMD code, `price` is denominated in
 * that currency and the VCR converts every line to AMD server-side at the
 * previous-business-day CBA rate; the fiscal receipt is always issued in
 * AMD. All foreign-priced items in one sale must share the same currency —
 * mixing two currencies, or AMD and foreign lines, in one sale is rejected
 * by the server. Omit the field (or pass `"AMD"`) for a native AMD line.
 * Pair with {@see RegisterSaleInput::withAutoSettle()} so the AMD total is
 * derived server-side, and preview the applied rate via
 * {@see \BlobSolutions\VcrAm\VcrClient::getExchangeRate()}.
 */
final readonly class SaleItem implements JsonSerializable
{
    /**
     * @param ?list<string> $emarks
     */
    public function __construct(
        public Offer $offer,
        public Department $department,
        public string $quantity,
        public string $price,
        public Unit $unit,
        public ?Discounts $discounts = null,
        public ?string $totalAmountTolerance = null,
        public ?array $emarks = null,
        public ?string $currency = null,
    ) {
        if (preg_match('/^\d+(\.\d+)?$/', $quantity) !== 1) {
            throw new InvalidArgumentException('quantity must be a non-negative decimal string (e.g. "1" or "1.500").');
        }

        if (preg_match('/^\d+(\.\d+)?$/', $price) !== 1) {
            throw new InvalidArgumentException('price must be a non-negative decimal string (e.g. "750" or "750.00").');
        }

        if ($totalAmountTolerance !== null && preg_match('/^\d+(\.\d+)?$/', $totalAmountTolerance) !== 1) {
            throw new InvalidArgumentException('totalAmountTolerance must be a non-negative decimal string (e.g. "0.01"). Omit the field for an exact match.');
        }

        if ($emarks !== null) {
            foreach ($emarks as $emark) {
                if (trim($emark) === '') {
                    throw new InvalidArgumentException('emarks entries must not be empty.');
                }
            }
        }

        if ($currency !== null && preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('currency must be a 3-letter ISO 4217 code (e.g. "USD"). Omit the field for a native AMD line.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'offer' => $this->offer,
            'department' => $this->department,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'unit' => $this->unit->value,
        ];

        if ($this->discounts !== null) {
            $payload['discounts'] = $this->discounts;
        }

        if ($this->totalAmountTolerance !== null) {
            $payload['totalAmountTolerance'] = $this->totalAmountTolerance;
        }

        if ($this->emarks !== null) {
            $payload['emarks'] = $this->emarks;
        }

        if ($this->currency !== null) {
            $payload['currency'] = $this->currency;
        }

        return $payload;
    }
}
