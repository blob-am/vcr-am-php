<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * One entry in the result of {@see \BlobSolutions\VcrAm\VcrClient::listCashiers()}.
 *
 * The `name` field is keyed by language code (typically the same value as the
 * inner {@see CashierLocalizedName::$language}), preserved as a map to match
 * the wire format.
 */
final readonly class CashierListItem
{
    /**
     * @param array<string, CashierLocalizedName> $name
     */
    public function __construct(
        public string $deskId,
        public int $internalId,
        public array $name,
    ) {
    }
}
