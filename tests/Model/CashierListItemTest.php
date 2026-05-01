<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\Model\CashierListItem;
use BlobSolutions\VcrAm\Model\CashierLocalizedName;
use PHPUnit\Framework\Assert;

it('is constructible with a localised name map keyed by language code', function (): void {
    $cashier = new CashierListItem(
        deskId: 'desk-1',
        internalId: 1,
        name: [
            'hy' => new CashierLocalizedName(Language::Armenian, 'Հաշվապահ'),
            'en' => new CashierLocalizedName(Language::English, 'Cashier'),
        ],
    );

    expect($cashier->deskId)->toBe('desk-1')
        ->and($cashier->internalId)->toBe(1)
        ->and($cashier->name)->toHaveCount(2);

    Assert::assertArrayHasKey('hy', $cashier->name);

    expect($cashier->name['hy']->language)->toBe(Language::Armenian)
        ->and($cashier->name['hy']->content)->toBe('Հաշվապահ');
});

it('is immutable (final readonly class)', function (): void {
    $reflection = new ReflectionClass(CashierListItem::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});

it('declares CashierLocalizedName as final readonly', function (): void {
    $reflection = new ReflectionClass(CashierLocalizedName::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});
