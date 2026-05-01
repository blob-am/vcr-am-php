<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Reason code for a sale refund. Wire values mirror the TypeScript SDK's
 * `REFUND_REASONS` constant verbatim — the API rejects unknown values.
 */
enum RefundReason: string
{
    case CustomerRequest = 'customer_request';
    case DefectiveGoods = 'defective_goods';
    case WrongGoods = 'wrong_goods';
    case CashierError = 'cashier_error';
    case DuplicateReceipt = 'duplicate_receipt';
    case Other = 'other';
}
