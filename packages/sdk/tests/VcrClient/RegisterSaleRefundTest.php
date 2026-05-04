<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RefundItemInput;
use BlobSolutions\VcrAm\Input\RegisterSaleRefundInput;
use BlobSolutions\VcrAm\Model\RegisterSaleRefundResponse;
use BlobSolutions\VcrAm\RefundReason;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeMinimalRefundInput(): RegisterSaleRefundInput
{
    return new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 4711,
    );
}

it('parses a successful refund response with crn and fiscal', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/refund-abc',
        'saleRefundId' => 8001,
        'crn' => '20250502-002',
        'receiptId' => 1001,
        'fiscal' => 'FA00012346',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerSaleRefund(makeMinimalRefundInput());

    expect($response)->toBeInstanceOf(RegisterSaleRefundResponse::class)
        ->and($response->urlId)->toBe('r/refund-abc')
        ->and($response->saleRefundId)->toBe(8001)
        ->and($response->crn)->toBe('20250502-002')
        ->and($response->receiptId)->toBe(1001)
        ->and($response->fiscal)->toBe('FA00012346');
});

it('parses a refund response with null crn and fiscal (SRC issuance pending)', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/refund-xyz',
        'saleRefundId' => 8002,
        'crn' => null,
        'receiptId' => 1002,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerSaleRefund(makeMinimalRefundInput());

    expect($response->crn)->toBeNull()
        ->and($response->fiscal)->toBeNull();
});

it('sends a POST request to /sales/refund with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/x',
        'saleRefundId' => 1,
        'crn' => null,
        'receiptId' => 1,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $input = new RegisterSaleRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        saleId: 4711,
        reason: RefundReason::CustomerRequest,
        items: [new RefundItemInput(srcId: 1001, quantity: '1')],
    );
    $client->registerSaleRefund($input);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/sales/refund')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'cashier' => ['deskId' => 'desk-1'],
        'saleId' => 4711,
        'reason' => 'customer_request',
        'items' => [
            ['srcId' => 1001, 'quantity' => '1'],
        ],
    ]);
});

it('surfaces server-side rejection as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'SALE_NOT_FOUND',
        'message' => 'Sale 4711 was not registered through this account.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(404, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->registerSaleRefund(makeMinimalRefundInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(404)
            ->and($e->apiErrorCode)->toBe('SALE_NOT_FOUND');
    }
});
