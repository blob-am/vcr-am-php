<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\OfferTitleType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Title of a {@see Offer::createNew() new offer}, in either of two flavours:
 *
 * - {@see self::universal()} — a single language-agnostic string;
 * - {@see self::localized()} — a per-language map (Armenian is mandatory),
 *   plus a strategy describing how the API should derive missing translations.
 */
final readonly class OfferTitle implements JsonSerializable
{
    /**
     * @param string|array<string, string> $content
     */
    private function __construct(
        public OfferTitleType $type,
        public string|array $content,
        public ?LocalizationStrategy $localizationStrategy,
    ) {
    }

    public static function universal(string $content): self
    {
        if (trim($content) === '') {
            throw new InvalidArgumentException('Universal offer title content must not be empty.');
        }

        return new self(OfferTitleType::Universal, $content, null);
    }

    /**
     * @param array<string, string> $content Per-language map; the key for
     *                                       Armenian ({@see Language::Armenian})
     *                                       is mandatory.
     */
    public static function localized(array $content, LocalizationStrategy $localizationStrategy): self
    {
        if (! array_key_exists(Language::Armenian->value, $content)) {
            throw new InvalidArgumentException('Localized offer title content requires the "hy" key (Armenian is mandatory).');
        }

        if (trim($content[Language::Armenian->value]) === '') {
            throw new InvalidArgumentException('Localized offer title "hy" content must not be empty.');
        }

        return new self(OfferTitleType::Localized, $content, $localizationStrategy);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [
            'type' => $this->type->value,
            'content' => $this->content,
        ];

        if ($this->localizationStrategy !== null) {
            $payload['localizationStrategy'] = $this->localizationStrategy->value;
        }

        return $payload;
    }
}
