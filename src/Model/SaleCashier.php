<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * Cashier as embedded in a sale detail response. Distinct from
 * {@see CashierListItem} (the listCashiers payload) — detail responses
 * use a localisation array rather than a per-language map.
 */
final readonly class SaleCashier
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
