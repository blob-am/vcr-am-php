<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Model\PrepaymentListItem;
use BlobSolutions\VcrAm\PrepaymentState;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makePrepaymentListJson(): string
{
    return json_encode([
        [
            'id' => 11,
            'createdAt' => '2026-04-15T12:34:56.789Z',
            'buyerTin' => '01234567',
            'cashAmount' => 1000,
            'nonCashAmount' => 0,
            'remaining' => 600,
            'state' => 'open',
        ],
        [
            'id' => 12,
            'createdAt' => '2026-04-16T08:00:00.000Z',
            'buyerTin' => null,
            'cashAmount' => 0,
            'nonCashAmount' => 900,
            'remaining' => 0,
            'state' => 'consumed',
        ],
    ], JSON_THROW_ON_ERROR);
}

it('returns a typed list with derived remaining and state', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makePrepaymentListJson()));

    $result = $client->listPrepayments();

    Assert::assertCount(2, $result);
    [$first, $second] = $result;

    expect($first)->toBeInstanceOf(PrepaymentListItem::class)
        ->and($first->id)->toBe(11)
        ->and($first->remaining)->toBe(600.0)
        ->and($first->state)->toBe(PrepaymentState::Open)
        ->and($second->state)->toBe(PrepaymentState::Consumed);
});

it('sends a GET /prepayments with no query when no filter is given', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listPrepayments();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/prepayments');
});

it('appends customerRef and state to the query when provided', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));

    $client->listPrepayments(customerRef: '01234567', state: PrepaymentState::Open);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    $uri = $request->getUri();
    parse_str($uri->getQuery(), $query);

    expect($uri->getPath())->toBe('/api/v1/prepayments')
        ->and($query['customerRef'] ?? null)->toBe('01234567')
        ->and($query['state'] ?? null)->toBe('open');
});
