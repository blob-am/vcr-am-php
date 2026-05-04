<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\Model\ClassifierSearchItem;
use BlobSolutions\VcrAm\OfferType;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('parses a list of classifier search items including one without a title', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        ['code' => '01.01.01', 'title' => 'Bread'],
        ['code' => '01.01.02'],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $items = $client->searchClassifier('bread', OfferType::Product, Language::English);

    Assert::assertCount(2, $items);
    [$first, $second] = $items;

    expect($first)->toBeInstanceOf(ClassifierSearchItem::class)
        ->and($first->code)->toBe('01.01.01')
        ->and($first->title)->toBe('Bread')
        ->and($second->code)->toBe('01.01.02')
        ->and($second->title)->toBeNull();
});

it('returns an empty list when the server has no matches', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $items = $client->searchClassifier('zzzz', OfferType::Service, Language::Russian);

    expect($items)->toBe([]);
});

it('builds the request URL with RFC 3986-encoded query params', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->searchClassifier('Հաց', OfferType::Product, Language::Armenian);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET');

    // URL-encoded Armenian "Հաց" is %D5%80%D5%A1%D6%81 in UTF-8.
    expect((string) $request->getUri())
        ->toContain('https://vcr.am/api/v1/searchByClassifier?')
        ->toContain('query=%D5%80%D5%A1%D6%81')
        ->toContain('type=product')
        ->toContain('language=hy');
});

it('rejects an empty query', function (): void {
    [$client] = makeMockedClient();

    $client->searchClassifier('   ', OfferType::Product, Language::Armenian);
})->throws(InvalidArgumentException::class, 'query must not be empty.');

it('trims whitespace before encoding the query into the URL', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->searchClassifier('   bread   ', OfferType::Product, Language::English);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect((string) $request->getUri())->toContain('query=bread');
});

it('rejects Language::Multi', function (): void {
    [$client] = makeMockedClient();

    $client->searchClassifier('bread', OfferType::Product, Language::Multi);
})->throws(InvalidArgumentException::class, 'Multi is not searchable');

it('throws VcrApiException on HTTP 400 with parsed error envelope', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'INVALID_TYPE',
        'message' => 'type must be product or service.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->searchClassifier('x', OfferType::Product, Language::English);
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->apiErrorCode)->toBe('INVALID_TYPE');
    }
});
