<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\Language;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Delivery instructions for sending the fiscal receipt to the buyer.
 */
final readonly class SendReceiptToBuyer implements JsonSerializable
{
    public function __construct(
        public string $email,
        public Language $language,
    ) {
        if (trim($email) === '') {
            throw new InvalidArgumentException('email must not be empty.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('email %s is not a valid address.', $email));
        }

        if ($language === Language::Multi) {
            throw new InvalidArgumentException('language cannot be Multi when sending a receipt — pick a concrete language.');
        }
    }

    /**
     * @return array{email: string, language: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'email' => $this->email,
            'language' => $this->language->value,
        ];
    }
}
