<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::createCashier()}.
 *
 * `password` is the cashier's terminal PIN — 4 to 8 numeric digits, used
 * to clock in/out at the physical or virtual register. The PIN is held in
 * a private property and redacted from `var_dump`/`print_r` output via
 * `__debugInfo()` so it can't accidentally leak into APM breadcrumbs or
 * logs that walk public properties.
 */
final readonly class CreateCashierInput implements JsonSerializable
{
    private string $password;

    public function __construct(
        public LocalizedName $name,
        string $password,
    ) {
        if (preg_match('/^\d{4,8}$/', $password) !== 1) {
            throw new InvalidArgumentException('password must be a 4-8 digit numeric PIN.');
        }

        $this->password = $password;
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

    /**
     * @return array{name: LocalizedName, password: string}
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'password' => '[REDACTED]',
        ];
    }
}
