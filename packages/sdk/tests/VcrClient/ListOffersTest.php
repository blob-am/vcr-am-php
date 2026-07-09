<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\Model\LocalizationEntry;
use BlobSolutions\VcrAm\Model\OfferDefaultDepartment;
use BlobSolutions\VcrAm\Model\OfferListItem;
use BlobSolutions\VcrAm\OfferType;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeOfferListJson(): string
{
    return json_encode([
        [
            'id' => 7,
            'externalId' => 'sku-coffee',
            'type' => 'product',
            'classifierCode' => '0901',
            'defaultMeasureUnit' => 'pc',
            'defaultDepartment' => ['internalId' => 3],
            'title' => [
                ['id' => 11, 'language' => 'hy', 'content' => 'Սուրճ'],
                ['id' => 12, 'language' => 'en', 'content' => 'Coffee'],
            ],
            'archivedAt' => null,
            'createdAt' => '2026-07-01T09:30:00.000Z',
        ],
        [
            'id' => 8,
            'externalId' => null,
            'type' => 'service',
            'classifierCode' => '96.09',
            'defaultMeasureUnit' => 'legacy-unit',
            'defaultDepartment' => ['internalId' => 4],
            'title' => [
                ['id' => 13, 'language' => 'multi', 'content' => 'Coca-Cola'],
            ],
            'archivedAt' => '2026-07-02T10:00:00.000Z',
            'createdAt' => '2026-06-01T08:00:00.000Z',
        ],
    ], JSON_THROW_ON_ERROR);
}

it('parses offers, including a legacy unit string and an archived offer', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeOfferListJson()));

    $result = $client->listOffers();

    Assert::assertCount(2, $result);
    [$first, $second] = $result;

    expect($first)->toBeInstanceOf(OfferListItem::class)
        ->and($first->id)->toBe(7)
        ->and($first->externalId)->toBe('sku-coffee')
        ->and($first->type)->toBe(OfferType::Product)
        ->and($first->classifierCode)->toBe('0901')
        ->and($first->defaultMeasureUnit)->toBe('pc')
        ->and($first->archivedAt)->toBeNull()
        ->and($first->createdAt)->toBe('2026-07-01T09:30:00.000Z');

    expect($first->defaultDepartment)->toBeInstanceOf(OfferDefaultDepartment::class)
        ->and($first->defaultDepartment->internalId)->toBe(3);

    Assert::assertCount(2, $first->title);
    [$firstTitle] = $first->title;
    expect($firstTitle)->toBeInstanceOf(LocalizationEntry::class)
        ->and($firstTitle->language)->toBe(Language::Armenian)
        ->and($firstTitle->content)->toBe('Սուրճ');

    // A legacy unit outside the Unit enum must still parse (defaultMeasureUnit is a plain string).
    Assert::assertCount(1, $second->title);
    [$secondTitle] = $second->title;
    expect($second->defaultMeasureUnit)->toBe('legacy-unit')
        ->and($second->type)->toBe(OfferType::Service)
        ->and($second->archivedAt)->toBe('2026-07-02T10:00:00.000Z')
        ->and($secondTitle->language)->toBe(Language::Multi);
});

it('sends filters as query parameters', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listOffers(externalId: 'sku-coffee', type: OfferType::Product, includeArchived: true);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET');
    parse_str($request->getUri()->getQuery(), $query);
    expect($query)->toBe([
        'externalId' => 'sku-coffee',
        'type' => 'product',
        'includeArchived' => 'true',
    ]);
});

it('sends no query string when no filters are given', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listOffers();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect((string) $request->getUri())->toBe('https://vcr.am/api/v1/offers');
});
