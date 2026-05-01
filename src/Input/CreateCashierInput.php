<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::createCashier()}.
 *
 * `password` is the cashier's terminal PIN — 4 to 8 numeric digits, used
 * to clock in/out at the physical or virtual register.
 */
final readonly class CreateCashierInput implements JsonSerializable
{
    public function __construct(
        public LocalizedName $name,
        public string $password,
    ) {
        if (preg_match('/^\d{4,8}$/', $password) !== 1) {
            throw new InvalidArgumentException('password must be a 4-8 digit numeric PIN.');
        }
    }

    /**
     * @return array{name: LocalizedName, password: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'password' => $this->password,
        ];
    }
}
