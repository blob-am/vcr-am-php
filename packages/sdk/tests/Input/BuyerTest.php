<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\BuyerType;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\SendReceiptToBuyer;
use BlobSolutions\VcrAm\Language;

it('serializes an individual buyer with no receipt as just type', function (): void {
    $buyer = Buyer::individual();

    expect($buyer->type)->toBe(BuyerType::Individual)
        ->and($buyer->tin)->toBeNull()
        ->and($buyer->jsonSerialize())->toBe(['type' => 'individual']);
});

it('serializes an individual buyer with a receipt request', function (): void {
    $buyer = Buyer::individual(new SendReceiptToBuyer('jane@example.com', Language::English));

    expect(json_encode($buyer, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'type' => 'individual',
            'receipt' => ['email' => 'jane@example.com', 'language' => 'en'],
        ], JSON_THROW_ON_ERROR));
});

it('serializes a business entity buyer with a TIN', function (): void {
    $buyer = Buyer::businessEntity('12345678');

    expect($buyer->type)->toBe(BuyerType::BusinessEntity)
        ->and($buyer->tin)->toBe('12345678')
        ->and($buyer->jsonSerialize())->toBe([
            'type' => 'business_entity',
            'tin' => '12345678',
        ]);
});

it('serializes a business entity buyer with both TIN and receipt', function (): void {
    $buyer = Buyer::businessEntity(
        '12345678',
        new SendReceiptToBuyer('billing@example.com', Language::Russian),
    );

    expect(json_encode($buyer, JSON_THROW_ON_ERROR))
        ->toBe(json_encode([
            'type' => 'business_entity',
            'tin' => '12345678',
            'receipt' => ['email' => 'billing@example.com', 'language' => 'ru'],
        ], JSON_THROW_ON_ERROR));
});

it('accepts a 10-digit sole-proprietor TIN', function (): void {
    $buyer = Buyer::businessEntity('1234567890');

    expect($buyer->tin)->toBe('1234567890');
});

it('rejects an empty TIN for a business entity', function (): void {
    Buyer::businessEntity('  ');
})->throws(InvalidArgumentException::class, 'TIN must be exactly 8 or 10 digits.');

it('rejects a TIN with non-digit characters', function (): void {
    Buyer::businessEntity('1234abcd');
})->throws(InvalidArgumentException::class, 'TIN must be exactly 8 or 10 digits.');

it('rejects a 7-digit TIN', function (): void {
    Buyer::businessEntity('1234567');
})->throws(InvalidArgumentException::class, 'TIN must be exactly 8 or 10 digits.');

it('rejects a 9-digit TIN', function (): void {
    Buyer::businessEntity('123456789');
})->throws(InvalidArgumentException::class, 'TIN must be exactly 8 or 10 digits.');

it('rejects an 11-digit TIN', function (): void {
    Buyer::businessEntity('12345678901');
})->throws(InvalidArgumentException::class, 'TIN must be exactly 8 or 10 digits.');

it('serializes optional email + phone on an individual buyer', function (): void {
    $buyer = Buyer::individual(
        receipt: null,
        email: 'jane@example.com',
        phone: '+37491234567',
    );

    expect($buyer->jsonSerialize())->toBe([
        'type' => 'individual',
        'email' => 'jane@example.com',
        'phone' => '+37491234567',
    ]);
});

it('serializes email + phone independently of receipt on a business buyer', function (): void {
    $buyer = Buyer::businessEntity(
        tin: '12345678',
        receipt: null,
        email: 'billing@example.com',
        phone: '+37491234567',
    );

    expect($buyer->jsonSerialize())->toBe([
        'type' => 'business_entity',
        'tin' => '12345678',
        'email' => 'billing@example.com',
        'phone' => '+37491234567',
    ]);
});

it('rejects a malformed email', function (): void {
    Buyer::individual(email: 'not-an-email');
})->throws(InvalidArgumentException::class, 'Email must be a valid address.');

it('rejects a phone without leading +', function (): void {
    Buyer::individual(phone: '37491234567');
})->throws(InvalidArgumentException::class, 'Phone must be in E.164 format');

it('rejects a phone with letters', function (): void {
    Buyer::individual(phone: '+374abcdef');
})->throws(InvalidArgumentException::class, 'Phone must be in E.164 format');
