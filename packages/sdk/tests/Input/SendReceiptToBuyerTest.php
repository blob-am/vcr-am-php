<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\SendReceiptToBuyer;
use BlobSolutions\VcrAm\Language;

it('serializes email + language to the wire shape', function (): void {
    $receipt = new SendReceiptToBuyer('jane@example.com', Language::English);

    expect($receipt->jsonSerialize())->toBe([
        'email' => 'jane@example.com',
        'language' => 'en',
    ]);
});

it('preserves email + language as readonly properties', function (): void {
    $receipt = new SendReceiptToBuyer('john@example.com', Language::Armenian);

    expect($receipt->email)->toBe('john@example.com')
        ->and($receipt->language)->toBe(Language::Armenian);
});

it('rejects an empty email', function (): void {
    new SendReceiptToBuyer('   ', Language::English);
})->throws(InvalidArgumentException::class, 'email must not be empty.');

it('rejects a malformed email address', function (): void {
    new SendReceiptToBuyer('not-an-email', Language::English);
})->throws(InvalidArgumentException::class, 'is not a valid address');

it('rejects Language::Multi (must pick a concrete language)', function (): void {
    new SendReceiptToBuyer('jane@example.com', Language::Multi);
})->throws(InvalidArgumentException::class, 'cannot be Multi');
