<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\LocalizationStrategy;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Per-language name plus a strategy describing how the API should derive
 * missing translations. Used by {@see CreateCashierInput::$name} and (later)
 * {@see CreateOfferInput} title fields.
 *
 * Distinct from {@see OfferTitle} — that type is a discriminated union
 * (universal vs. localised) for offer titles specifically. `LocalizedName`
 * is the simpler "always localised" shape.
 */
final readonly class LocalizedName implements JsonSerializable
{
    /**
     * @param array<string, string> $value Per-language map; the Armenian key
     *                                     ({@see Language::Armenian}) is
     *                                     mandatory.
     */
    public function __construct(
        public array $value,
        public LocalizationStrategy $localizationStrategy,
    ) {
        if (! array_key_exists(Language::Armenian->value, $value)) {
            throw new InvalidArgumentException('LocalizedName value requires the "hy" key (Armenian is mandatory).');
        }

        if (trim($value[Language::Armenian->value]) === '') {
            throw new InvalidArgumentException('LocalizedName "hy" content must not be empty.');
        }
    }

    /**
     * @return array{value: array<string, string>, localizationStrategy: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'localizationStrategy' => $this->localizationStrategy->value,
        ];
    }
}
