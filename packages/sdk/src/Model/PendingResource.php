<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * A document VCR persisted despite the error.
 *
 * Present on a 502 when SRC was unreachable, and on a 409 when SRC refused the
 * document. Either way the request was NOT lost, so never resend blindly —
 * {@see $mayResubmit} says whether sending it again is the right move or the
 * way to a second fiscal receipt. A fiscal receipt cannot be deleted, only
 * refunded.
 *
 * Its absence on an error means nothing was created and the call can be
 * repeated normally.
 *
 * `type` is a plain string rather than an enum on purpose: if the server
 * starts queueing a resource type an older SDK does not know about, an enum
 * would fail to parse and take the real error message down with it.
 */
final readonly class PendingResource
{
    public function __construct(
        /** Which collection {@see $id} belongs to, e.g. `sale` or `prepayment`. */
        public string $type,
        public int $id,
        /** Path to read the outcome from, e.g. `/api/v1/sales/5122`. */
        public string $statusUrl,
        /**
         * Whether sending this exact request again is safe.
         *
         * `false` — the document is still VCR's to settle: SRC may have
         * registered it already, or VCR will submit it again for you.
         * Resending risks a second fiscal receipt. Read {@see $statusUrl}
         * instead.
         *
         * `true` — SRC registered nothing and VCR will not send it again (the
         * merchant has late fiscalization off, which is the default).
         * Resubmitting is how the document gets fiscalized.
         *
         * `null` when the server predates the field. Treat that as `false` —
         * the conservative reading, and what those servers did.
         */
        public ?bool $mayResubmit = null,
    ) {
    }
}
