<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * The single tender an {@see Input\AutoSettle} sale is charged to.
 *
 * Only cash / non-cash: prepayment and compensation are ledger-draw semantics
 * that must be stated explicitly via {@see Input\SaleAmount}, so they are not
 * valid auto-settle tenders.
 */
enum AutoSettleTender: string
{
    case Cash = 'cash';
    case NonCash = 'nonCash';
}
