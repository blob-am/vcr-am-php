<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Cashier as embedded in a detail response — used by both {@see SaleDetail}
 * and {@see PrepaymentDetail}. Distinct from {@see CashierListItem} (the
 * listCashiers payload): detail responses use a localisation array rather
 * than a per-language map, and each entry carries its own database id.
 */
final readonly class Cashier
{
    /**
     * @param list<LocalizationEntry> $name
     */
    public function __construct(
        public int $internalId,
        public string $deskId,
        public array $name,
    ) {
    }
}
