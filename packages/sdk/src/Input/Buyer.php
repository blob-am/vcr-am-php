<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use BlobSolutions\VcrAm\BuyerType;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Buyer side of a sale receipt — either an individual (no fiscal id) or a
 * business entity (TIN required). Construct via the {@see self::individual()}
 * or {@see self::businessEntity()} factories so invalid combinations are
 * unrepresentable.
 *
 * Optional `email` and `phone` capture buyer contact independently of the
 * receipt-delivery flag. Either feeds the desk-side prepayment lookup
 * banner so a later sale to the same buyer can discover their open
 * prepayments by typing the same value. Both pass through to the server
 * unchanged; the server validates phone is real (libphonenumber).
 */
final readonly class Buyer implements JsonSerializable
{
    private function __construct(
        public BuyerType $type,
        public ?string $tin,
        public ?string $email,
        public ?string $phone,
        public ?SendReceiptToBuyer $receipt,
    ) {
    }

    public static function individual(
        ?SendReceiptToBuyer $receipt = null,
        ?string $email = null,
        ?string $phone = null,
    ): self {
        return new self(
            BuyerType::Individual,
            null,
            self::validateEmail($email),
            self::validatePhone($phone),
            $receipt,
        );
    }

    public static function businessEntity(
        string $tin,
        ?SendReceiptToBuyer $receipt = null,
        ?string $email = null,
        ?string $phone = null,
    ): self {
        // Armenian TIN is exactly 8 (legal-entity) or 10 (sole-proprietor) digits.
        // Catch malformed input client-side before round-tripping to the SRC.
        if (preg_match('/^\d{8}$|^\d{10}$/', $tin) !== 1) {
            throw new InvalidArgumentException('TIN must be exactly 8 or 10 digits.');
        }

        return new self(
            BuyerType::BusinessEntity,
            $tin,
            self::validateEmail($email),
            self::validatePhone($phone),
            $receipt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = ['type' => $this->type->value];

        if ($this->tin !== null) {
            $payload['tin'] = $this->tin;
        }

        if ($this->email !== null) {
            $payload['email'] = $this->email;
        }

        if ($this->phone !== null) {
            $payload['phone'] = $this->phone;
        }

        if ($this->receipt !== null) {
            $payload['receipt'] = $this->receipt;
        }

        return $payload;
    }

    private static function validateEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Email must be a valid address.');
        }

        return $email;
    }

    /**
     * Phone must be in E.164 format ("+37491234567"). Locale-typed input
     * ("099 12 34 56") is the caller's responsibility — parse with
     * libphonenumber-for-php or similar before constructing the Buyer.
     */
    private static function validatePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        if (preg_match('/^\+\d{8,15}$/', $phone) !== 1) {
            throw new InvalidArgumentException(
                'Phone must be in E.164 format (e.g. "+37491234567").',
            );
        }

        return $phone;
    }
}
