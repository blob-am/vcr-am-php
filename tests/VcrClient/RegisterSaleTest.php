<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Model\RegisterSaleResponse;
use BlobSolutions\VcrAm\Unit;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeMinimalSaleInput(): RegisterSaleInput
{
    return new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: [
            new SaleItem(
                offer: Offer::existing('sku-bread'),
                department: new Department(5),
                quantity: '2',
                price: '750',
                unit: Unit::Piece,
            ),
        ],
        amount: new SaleAmount(cash: '1500'),
        buyer: Buyer::individual(),
    );
}

it('parses a successful registerSale response into a typed DTO', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/abc123',
        'saleId' => 4711,
        'crn' => '20250502-001',
        'srcReceiptId' => 999,
        'fiscal' => 'FA00012345',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerSale(makeMinimalSaleInput());

    expect($response)->toBeInstanceOf(RegisterSaleResponse::class)
        ->and($response->urlId)->toBe('r/abc123')
        ->and($response->saleId)->toBe(4711)
        ->and($response->crn)->toBe('20250502-001')
        ->and($response->srcReceiptId)->toBe(999)
        ->and($response->fiscal)->toBe('FA00012345');
});

it('sends a POST request to /sales with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/abc',
        'saleId' => 1,
        'crn' => '0',
        'srcReceiptId' => 1,
        'fiscal' => '0',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->registerSale(makeMinimalSaleInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/sales')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'cashier' => ['deskId' => 'desk-1'],
        'items' => [
            [
                'offer' => ['externalId' => 'sku-bread'],
                'department' => ['id' => 5],
                'quantity' => '2',
                'price' => '750',
                'unit' => 'pc',
            ],
        ],
        'amount' => ['cash' => '1500'],
        'buyer' => ['type' => 'individual'],
    ]);
});

it('surfaces server-side validation errors as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'INVALID_TIN',
        'message' => 'TIN must be 8 or 10 digits.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(422, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->registerSale(makeMinimalSaleInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(422)
            ->and($e->apiErrorCode)->toBe('INVALID_TIN')
            ->and($e->apiErrorMessage)->toBe('TIN must be 8 or 10 digits.');
    }
});

it('surfaces transport failures as VcrNetworkException', function (): void {
    [$client, $mock] = makeMockedClient();
    $cause = new class ('TLS handshake failed') extends RuntimeException implements Psr\Http\Client\ClientExceptionInterface {};
    $mock->addException($cause);

    $client->registerSale(makeMinimalSaleInput());
})->throws(BlobSolutions\VcrAm\Exception\VcrNetworkException::class);

it('surfaces a malformed response as VcrValidationException', function (): void {
    [$client, $mock] = makeMockedClient();
    // Server returns 200 OK but with a body that does not match RegisterSaleResponse
    $body = json_encode(['urlId' => 'r/abc'], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->registerSale(makeMinimalSaleInput());
})->throws(BlobSolutions\VcrAm\Exception\VcrValidationException::class);
