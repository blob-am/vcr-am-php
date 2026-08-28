<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * A document VCR persisted and queued for automatic resubmission to SRC.
 *
 * Present on a 502 when SRC was merely unreachable. It means the request was
 * NOT lost: VCR owns the work and a background sweep usually completes it
 * within minutes. Read the document back at {@see $statusUrl} to learn the
 * outcome — and do NOT resend the original request, which would produce a
 * second fiscal receipt. A fiscal receipt cannot be deleted, only refunded.
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
    ) {
    }
}
