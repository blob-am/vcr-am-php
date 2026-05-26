<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Lifecycle state of a prepayment, derived server-side from the
 * PrepaymentLedger. Returned on every prepayment detail and list item.
 *
 * - Open      — remaining > 0; can still be applied to a sale or refunded.
 * - Consumed  — fully applied to subsequent sales; remaining == 0.
 * - Refunded  — a PrepaymentRefund row exists. Always full per AM Govt
 *               Decision 1976-Ն Annex 3 §29 (no partial prepayment refunds).
 */
enum PrepaymentState: string
{
    case Open = 'open';
    case Consumed = 'consumed';
    case Refunded = 'refunded';
}
