<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Response payload from {@see \BlobSolutions\VcrAm\VcrClient::createCashier()}.
 *
 * `id` is the VCR.AM internal numeric id; `deskId` is the customer-facing
 * string identifier the cashier uses to clock in.
 */
final readonly class CreateCashierResponse
{
    public function __construct(
        public int $id,
        public string $deskId,
    ) {
    }
}
