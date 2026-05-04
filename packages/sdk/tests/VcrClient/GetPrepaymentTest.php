<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Model\Cashier;
use BlobSolutions\VcrAm\Model\PrepaymentDetail;
use BlobSolutions\VcrAm\Model\PrepaymentRefund;
use BlobSolutions\VcrAm\Model\Receipt;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function makeSamplePrepaymentDetailJson(): string
{
    return json_encode([
        'id' => 9001,
        'createdAt' => '2026-05-02T08:30:00.000Z',
        'buyerTin' => null,
        'cashAmount' => 5000,
        'nonCashAmount' => 0,
        'receipt' => [
            'srcId' => 999,
            'time' => '1746169800000',
            'tin' => '12345678',
            'fiscal' => 'FA00099001',
            'sn' => 'SN-2026-001',
            'address' => 'Yerevan, Republic Square 1',
            'total' => 5000,
            'taxpayer' => 'Test LLC',
            'change' => 0,
        ],
        'refund' => null,
        'cashier' => [
            'internalId' => 1,
            'deskId' => 'desk-1',
            'name' => [
                ['id' => 100, 'language' => 'hy', 'content' => 'Հաշվապահ'],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

it('parses a complete prepayment detail with an issued receipt and no refund', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeSamplePrepaymentDetailJson()));

    $prepayment = $client->getPrepayment(9001);

    expect($prepayment)->toBeInstanceOf(PrepaymentDetail::class)
        ->and($prepayment->id)->toBe(9001)
        ->and($prepayment->createdAt)->toBe('2026-05-02T08:30:00.000Z')
        ->and($prepayment->buyerTin)->toBeNull()
        ->and($prepayment->cashAmount)->toBe(5000.0)
        ->and($prepayment->refund)->toBeNull()
        ->and($prepayment->cashier)->toBeInstanceOf(Cashier::class)
        ->and($prepayment->cashier->deskId)->toBe('desk-1')
        ->and($prepayment->receipt)->toBeInstanceOf(Receipt::class);

    Assert::assertNotNull($prepayment->receipt);
    expect($prepayment->receipt->fiscal)->toBe('FA00099001')
        ->and($prepayment->receipt->total)->toBe(5000.0);
});

it('parses a prepayment detail with a refund and null receipt', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'id' => 9002,
        'createdAt' => '2026-05-02T09:00:00.000Z',
        'buyerTin' => '12345678',
        'cashAmount' => 0,
        'nonCashAmount' => 3000,
        'receipt' => null,
        'refund' => [
            'nonCashAmount' => 3000,
            'cashAmount' => 0,
            'receipt' => null,
        ],
        'cashier' => [
            'internalId' => 2,
            'deskId' => 'desk-2',
            'name' => [],
        ],
    ], JSON_THROW_ON_ERROR);
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $prepayment = $client->getPrepayment(9002);

    expect($prepayment->receipt)->toBeNull()
        ->and($prepayment->buyerTin)->toBe('12345678')
        ->and($prepayment->refund)->toBeInstanceOf(PrepaymentRefund::class);

    Assert::assertNotNull($prepayment->refund);
    expect($prepayment->refund->nonCashAmount)->toBe(3000.0)
        ->and($prepayment->refund->cashAmount)->toBe(0.0)
        ->and($prepayment->refund->receipt)->toBeNull();
});

it('sends a GET request to /prepayments/{id} with the bearer token', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeSamplePrepaymentDetailJson()));

    $client->getPrepayment(9001);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/prepayments/9001')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key');
});

it('rejects a negative prepaymentId', function (): void {
    [$client] = makeMockedClient();

    $client->getPrepayment(-1);
})->throws(InvalidArgumentException::class, 'prepaymentId must be non-negative.');

it('throws VcrApiException on HTTP 404', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(404, ['Content-Type' => 'application/json'], '{"code":"NOT_FOUND","message":"Prepayment 9001 not found"}'));

    try {
        $client->getPrepayment(9001);
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(404)
            ->and($e->apiErrorCode)->toBe('NOT_FOUND');
    }
});
