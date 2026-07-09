<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Model\OfferListItem;
use BlobSolutions\VcrAm\OfferType;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;

function singleOfferJson(): string
{
    return json_encode([
        'id' => 7,
        'externalId' => 'sku-coffee',
        'type' => 'product',
        'classifierCode' => '0901',
        'defaultMeasureUnit' => 'pc',
        'defaultDepartment' => ['internalId' => 3],
        'title' => [
            ['id' => 11, 'language' => 'hy', 'content' => 'Սուրճ'],
        ],
        'archivedAt' => null,
        'createdAt' => '2026-07-01T09:30:00.000Z',
    ], JSON_THROW_ON_ERROR);
}

it('GETs /offers/{id} and parses a single offer', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], singleOfferJson()));

    $offer = $client->getOffer(7);

    expect($offer)->toBeInstanceOf(OfferListItem::class)
        ->and($offer->id)->toBe(7)
        ->and($offer->type)->toBe(OfferType::Product)
        ->and($offer->defaultDepartment->internalId)->toBe(3);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);
    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/offers/7');
});

it('rejects a negative offer id', function (): void {
    [$client] = makeMockedClient();

    $client->getOffer(-1);
})->throws(InvalidArgumentException::class, 'offerId must be non-negative.');
