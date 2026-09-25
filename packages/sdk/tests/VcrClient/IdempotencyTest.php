<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;
use BlobSolutions\VcrAm\VcrClient;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;

function makeIdempotencySaleInput(): RegisterSaleInput
{
    return new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: [
            new SaleItem(
                offer: Offer::existing('sku-bread'),
                department: new Department(5),
                quantity: '1',
                price: '750',
                unit: Unit::Piece,
            ),
        ],
        amount: new SaleAmount(cash: '750'),
        buyer: Buyer::individual(),
    );
}

function saleResponseBody(): string
{
    return json_encode([
        'urlId' => 'r/abc',
        'saleId' => 1,
        'crn' => '0',
        'srcReceiptId' => 1,
        'fiscal' => '0',
    ], JSON_THROW_ON_ERROR);
}

it('sends the Idempotency-Key header when a key is given', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], saleResponseBody()));

    $client->registerSale(makeIdempotencySaleInput(), 'order-4821:sale');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getHeaderLine('Idempotency-Key'))->toBe('order-4821:sale');
});

it('sends no Idempotency-Key header when none is given', function (): void {
    // The header must be absent rather than empty: an empty one is a 400 at
    // the server, which would break every caller that has not adopted keys.
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], saleResponseBody()));

    $client->registerSale(makeIdempotencySaleInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->hasHeader('Idempotency-Key'))->toBeFalse();
});

it('rejects an empty key before it reaches the network', function (): void {
    [$client, $mock] = makeMockedClient();

    expect(fn () => $client->registerSale(makeIdempotencySaleInput(), ''))
        ->toThrow(InvalidArgumentException::class);

    expect($mock->getRequests())->toBeEmpty();
});

it('rejects a key longer than the server accepts', function (): void {
    [$client, $mock] = makeMockedClient();
    $tooLong = str_repeat('k', VcrClient::MAX_IDEMPOTENCY_KEY_LENGTH + 1);

    expect(fn () => $client->registerSale(makeIdempotencySaleInput(), $tooLong))
        ->toThrow(InvalidArgumentException::class);

    expect($mock->getRequests())->toBeEmpty();
});

it('accepts a key of exactly the maximum length', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], saleResponseBody()));
    $maxLength = str_repeat('k', VcrClient::MAX_IDEMPOTENCY_KEY_LENGTH);

    $client->registerSale(makeIdempotencySaleInput(), $maxLength);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getHeaderLine('Idempotency-Key'))->toBe($maxLength);
});

it('measures the key in characters, not bytes', function (): void {
    // mb_strlen, so a key built from non-ASCII text is not rejected for being
    // two bytes per character.
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], saleResponseBody()));
    $armenian = str_repeat('ա', VcrClient::MAX_IDEMPOTENCY_KEY_LENGTH);

    $client->registerSale(makeIdempotencySaleInput(), $armenian);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getHeaderLine('Idempotency-Key'))->toBe($armenian);
});
