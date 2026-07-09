<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\OfferTitle;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\Model\OfferListItem;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function updatedOfferJson(): string
{
    return json_encode([
        'id' => 7,
        'externalId' => 'sku-coffee',
        'type' => 'product',
        'classifierCode' => '0901',
        'defaultMeasureUnit' => 'pc',
        'defaultDepartment' => ['internalId' => 3],
        'title' => [
            ['id' => 21, 'language' => 'hy', 'content' => 'Լատտե'],
        ],
        'archivedAt' => null,
        'createdAt' => '2026-07-01T09:30:00.000Z',
    ], JSON_THROW_ON_ERROR);
}

it('PATCHes /offers/{id} with the new title and parses the updated offer', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], updatedOfferJson()));

    $offer = $client->updateOffer(
        7,
        OfferTitle::localized(['hy' => 'Լատտե'], LocalizationStrategy::Transliteration),
    );

    Assert::assertCount(1, $offer->title);
    [$title] = $offer->title;
    expect($offer)->toBeInstanceOf(OfferListItem::class)
        ->and($offer->id)->toBe(7)
        ->and($title->content)->toBe('Լատտե');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('PATCH')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/offers/7');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'title' => [
            'type' => 'localized',
            'content' => ['hy' => 'Լատտե'],
            'localizationStrategy' => 'transliteration',
        ],
    ]);
});

it('accepts a universal title', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], updatedOfferJson()));

    $client->updateOffer(7, OfferTitle::universal('Coca-Cola'));

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'title' => [
            'type' => 'universal',
            'content' => 'Coca-Cola',
        ],
    ]);
});

it('rejects a negative offer id', function (): void {
    [$client] = makeMockedClient();

    $client->updateOffer(-1, OfferTitle::universal('X'));
})->throws(InvalidArgumentException::class, 'offerId must be non-negative.');
