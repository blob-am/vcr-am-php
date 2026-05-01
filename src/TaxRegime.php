<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Tax regime under which a department operates. Wire values mirror the
 * TypeScript SDK's `TAX_REGIMES` constant verbatim — the API rejects
 * unknown values.
 */
enum TaxRegime: string
{
    case Vat = 'vat';
    case VatExempt = 'vat_exempt';
    case TurnoverTax = 'turnover_tax';
    case MicroEnterprise = 'micro_enterprise';
}
