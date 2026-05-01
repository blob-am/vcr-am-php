<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\PrepaymentAmount;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentInput;
use BlobSolutions\VcrAm\Model\RegisterPrepaymentResponse;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeMinimalPrepaymentInput(): RegisterPrepaymentInput
{
    return new RegisterPrepaymentInput(
        cashier: CashierId::byDeskId('desk-1'),
        amount: new PrepaymentAmount(cash: '5000'),
        buyer: Buyer::individual(),
    );
}

it('parses a successful registerPrepayment response with crn and fiscal', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/prepay-abc',
        'prepaymentId' => 9001,
        'crn' => '20260502-001',
        'receiptId' => 5001,
        'fiscal' => 'FA00099001',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerPrepayment(makeMinimalPrepaymentInput());

    expect($response)->toBeInstanceOf(RegisterPrepaymentResponse::class)
        ->and($response->urlId)->toBe('r/prepay-abc')
        ->and($response->prepaymentId)->toBe(9001)
        ->and($response->crn)->toBe('20260502-001')
        ->and($response->receiptId)->toBe(5001)
        ->and($response->fiscal)->toBe('FA00099001');
});

it('parses a registerPrepayment response with null crn and fiscal', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/x',
        'prepaymentId' => 9002,
        'crn' => null,
        'receiptId' => 5002,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $response = $client->registerPrepayment(makeMinimalPrepaymentInput());

    expect($response->crn)->toBeNull()
        ->and($response->fiscal)->toBeNull();
});

it('sends a POST request to /prepayments with the JSON-encoded input', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'urlId' => 'r/x',
        'prepaymentId' => 1,
        'crn' => null,
        'receiptId' => 1,
        'fiscal' => null,
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $client->registerPrepayment(makeMinimalPrepaymentInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/prepayments')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json');

    $sentBody = json_decode((string) $request->getBody(), associative: true, flags: JSON_THROW_ON_ERROR);
    expect($sentBody)->toBe([
        'cashier' => ['deskId' => 'desk-1'],
        'amount' => ['cash' => '5000'],
        'buyer' => ['type' => 'individual'],
    ]);
});

it('surfaces server-side rejection as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $errorBody = json_encode([
        'code' => 'CASHIER_NOT_REGISTERED',
        'message' => 'Cashier desk-1 is not assigned to this account.',
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(403, ['Content-Type' => 'application/json'], $errorBody));

    try {
        $client->registerPrepayment(makeMinimalPrepaymentInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(403)
            ->and($e->apiErrorCode)->toBe('CASHIER_NOT_REGISTERED');
    }
});
