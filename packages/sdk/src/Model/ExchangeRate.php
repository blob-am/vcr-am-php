<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Model;

/**
 * The AMD conversion rate the VCR would apply to a foreign-currency sale
 * registered now — the CBA mid-market rate published on the previous business
 * day (Tax Code art. 16, HO-234-N). Returned by
 * {@see \BlobSolutions\VcrAm\VcrClient::getExchangeRate()}.
 *
 * `ratePerUnit` is AMD per one unit of `currency` (CBA rate / amount).
 * `amount` is the CBA multi-lot multiplier (1, 100, 1000, ...). `rateDate` is
 * the Yerevan date whose rate was applied (the previous business day) and
 * `saleDate` the calendar date the rate resolves for — both `YYYY-MM-DD`.
 * `source` is `"CBA"` today; kept as a plain string so a future provider can
 * never make the response fail to parse.
 */
final readonly class ExchangeRate
{
    public function __construct(
        public string $currency,
        public float $ratePerUnit,
        public int $amount,
        public string $rateDate,
        public string $saleDate,
        public string $ruleVersion,
        public string $source,
    ) {
    }
}
