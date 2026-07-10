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
 *
 * A sale settles by exactly one of two payment shapes, never both:
 *
 *   - `amount` — explicit AMD per tender ({@see SaleAmount});
 *   - `autoSettle` — the VCR derives the whole AMD cart total and charges it
 *     to a single tender ({@see AutoSettle}). Required for foreign-currency
 *     sales, where the AMD total isn't knowable client-side.
 *
 * Use {@see self::withAmount()} / {@see self::withAutoSettle()} for a clear
 * call site; the promoted constructor stays available for backward
 * compatibility (pass `null` for `$amount` and a non-null `$autoSettle` for
 * the derived-total mode).
 */
final readonly class RegisterSaleInput implements JsonSerializable
{
    /**
     * @param list<SaleItem> $items      Must contain at least one item; an empty
     *                                   list is rejected at runtime (defence in
     *                                   depth — callers may construct inputs from
     *                                   decoded JSON / config where the type
     *                                   system can't enforce non-emptiness).
     * @param ?SaleAmount    $amount     Explicit AMD amounts. Provide exactly one
     *                                   of `$amount` or `$autoSettle`.
     * @param ?AutoSettle    $autoSettle Derived-total settlement. Provide exactly
     *                                   one of `$amount` or `$autoSettle`.
     * @param ?string        $comment    Optional merchant-internal note (e.g. an
     *                                   external payment reference). Max 500 chars;
     *                                   the server strips control characters and
     *                                   trims whitespace. Purely internal — never
     *                                   printed on the buyer's receipt nor sent to
     *                                   the tax authority.
     */
    public function __construct(
        public CashierId $cashier,
        public array $items,
        public ?SaleAmount $amount,
        public Buyer $buyer,
        public ?AutoSettle $autoSettle = null,
        public ?string $comment = null,
    ) {
        if ($items === []) {
            throw new InvalidArgumentException('A sale must contain at least one item.');
        }

        $hasAmount = $amount !== null;
        $hasAutoSettle = $autoSettle !== null;

        if ($hasAmount === $hasAutoSettle) {
            throw new InvalidArgumentException(
                'Provide exactly one of `amount` (explicit AMD per tender) or `autoSettle` (VCR derives the total).',
            );
        }
    }

    /**
     * Settle the sale with explicit AMD amounts per tender.
     *
     * @param list<SaleItem> $items
     */
    public static function withAmount(CashierId $cashier, array $items, SaleAmount $amount, Buyer $buyer, ?string $comment = null): self
    {
        return new self($cashier, $items, $amount, $buyer, comment: $comment);
    }

    /**
     * Let the VCR derive the whole AMD cart total and settle it on one tender.
     * The natural choice for foreign-currency sales.
     *
     * @param list<SaleItem> $items
     */
    public static function withAutoSettle(CashierId $cashier, array $items, AutoSettle $autoSettle, Buyer $buyer, ?string $comment = null): self
    {
        return new self($cashier, $items, null, $buyer, $autoSettle, $comment);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        // Key order is kept stable (`cashier, items, amount, buyer`) for the
        // amount path so it stays byte-identical to earlier releases; the
        // optional `autoSettle` is appended. JSON object order is not
        // semantically significant, but a stable shape keeps wire snapshots and
        // golden tests from churning.
        $payload = [
            'cashier' => $this->cashier,
            'items' => $this->items,
        ];

        if ($this->amount !== null) {
            $payload['amount'] = $this->amount;
        }

        $payload['buyer'] = $this->buyer;

        if ($this->autoSettle !== null) {
            $payload['autoSettle'] = $this->autoSettle;
        }

        if ($this->comment !== null) {
            $payload['comment'] = $this->comment;
        }

        return $payload;
    }
}
