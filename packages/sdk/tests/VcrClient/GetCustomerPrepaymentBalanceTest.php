<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Model\CustomerOpenPrepayment;
use BlobSolutions\VcrAm\Model\CustomerPrepaymentBalance;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeCustomerBalanceJson(): string
{
    return json_encode([
        'entityId' => 42,
        'customerRef' => '01234567',
        'balance' => 1500,
        'openPrepayments' => [
            [
                'prepaymentId' => 11,
                'createdAt' => '2026-04-15T12:34:56.789Z',
                'cashAmount' => 1000,
                'nonCashAmount' => 0,
                'buyerTin' => '01234567',
                'remaining' => 600,
            ],
            [
                'prepaymentId' => 12,
                'createdAt' => '2026-04-16T08:00:00.000Z',
                'cashAmount' => 0,
                'nonCashAmount' => 900,
                'buyerTin' => '01234567',
                'remaining' => 900,
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

it('parses an entity-scoped balance with FIFO open prepayments', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeCustomerBalanceJson()));

    $balance = $client->getCustomerPrepaymentBalance('01234567');

    expect($balance)->toBeInstanceOf(CustomerPrepaymentBalance::class)
        ->and($balance->entityId)->toBe(42)
        ->and($balance->customerRef)->toBe('01234567')
        ->and($balance->balance)->toBe(1500.0);

    Assert::assertCount(2, $balance->openPrepayments);
    [$first, $second] = $balance->openPrepayments;

    expect($first)->toBeInstanceOf(CustomerOpenPrepayment::class)
        ->and($first->prepaymentId)->toBe(11)
        ->and($first->remaining)->toBe(600.0)
        ->and($second->remaining)->toBe(900.0);
});

it('sends a GET /prepayments/balance with the customerRef query', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeCustomerBalanceJson()));

    $client->getCustomerPrepaymentBalance('01234567');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    parse_str($request->getUri()->getQuery(), $query);

    expect($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/api/v1/prepayments/balance')
        ->and($query['customerRef'] ?? null)->toBe('01234567');
});

it('trims whitespace before validating customerRef', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeCustomerBalanceJson()));

    $client->getCustomerPrepaymentBalance('  01234567  ');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    parse_str($request->getUri()->getQuery(), $query);
    expect($query['customerRef'] ?? null)->toBe('01234567');
});

it('rejects empty customerRef', function (): void {
    [$client] = makeMockedClient();
    $client->getCustomerPrepaymentBalance('   ');
})->throws(InvalidArgumentException::class, 'customerRef must not be empty.');
