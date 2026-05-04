<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput;
use BlobSolutions\VcrAm\Model\RegisterPrepaymentRefundResponse;
use BlobSolutions\VcrAm\RefundReason;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeMinimalPrepaymentRefundInput(): RegisterPrepaymentRefundInput
{
    return new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: 9001,
    );
}

it('parses a successful prepayment refund response', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/prepay-refund-abc',
        'prepaymentRefundId' => 11001,
        'crn' => '20260502-009',
        'receiptId' => 6001,
        'fiscal' => 'FA00099009',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerPrepaymentRefund(makeMinimalPrepaymentRefundInput());

    expect($response)->toBeInstanceOf(RegisterPrepaymentRefundResponse::class)
        ->and($response->urlId)->toBe('r/prepay-refund-abc')
        ->and($response->prepaymentRefundId)->toBe(11001)
        ->and($response->crn)->toBe('20260502-009')
        ->and($response->receiptId)->toBe(6001)
        ->and($response->fiscal)->toBe('FA00099009');
});

it('parses a refund response with null crn and fiscal', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/x',
        'prepaymentRefundId' => 11002,
        'crn' => null,
        'receiptId' => 6002,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerPrepaymentRefund(makeMinimalPrepaymentRefundInput());

    expect($response->crn)->toBeNull()
        ->and($response->fiscal)->toBeNull();
});

it('sends a POST request to /prepayments/refund with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/x',
        'prepaymentRefundId' => 1,
        'crn' => null,
        'receiptId' => 1,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $input = new RegisterPrepaymentRefundInput(
        cashier: CashierId::byDeskId('desk-1'),
        prepaymentId: 9001,
        reason: RefundReason::CustomerRequest,
    );
    $client->registerPrepaymentRefund($input);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/prepayments/refund')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'cashier' => ['deskId' => 'desk-1'],
        'prepaymentId' => 9001,
        'reason' => 'customer_request',
    ]);
});

it('surfaces server-side rejection as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'PREPAYMENT_ALREADY_REFUNDED',
        'message' => 'Prepayment 9001 has already been refunded.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(409, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->registerPrepaymentRefund(makeMinimalPrepaymentRefundInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(409)
            ->and($e->apiErrorCode)->toBe('PREPAYMENT_ALREADY_REFUNDED');
    }
});
