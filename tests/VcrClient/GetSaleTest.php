<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\Model\Cashier;
use BlobSolutions\VcrAm\Model\Receipt;
use BlobSolutions\VcrAm\Model\SaleDetail;
use BlobSolutions\VcrAm\Model\SaleItem;
use BlobSolutions\VcrAm\Model\SaleRefund;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\TaxRegime;
use BlobSolutions\VcrAm\Unit;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

/**
 * @return string A JSON-encoded sale detail with one item, no refunds, an
 *                issued receipt, and a single Armenian localisation per
 *                titled field. Mirrors `saleDetailResponseSchema` in the TS
 *                SDK.
 */
function makeSampleSaleDetailJson(): string
{
    return json_encode([
        'id' => 4711,
        'createdAt' => '2026-05-02T08:30:00.000Z',
        'buyerTin' => null,
        'cashAmount' => 1500,
        'nonCashAmount' => 0,
        'prepaymentAmount' => 0,
        'compensationAmount' => 0,
        'receipt' => [
            'srcId' => 999,
            'time' => '1746169800000',
            'tin' => '12345678',
            'fiscal' => 'FA00012345',
            'sn' => 'SN-2026-001',
            'address' => 'Yerevan, Republic Square 1',
            'total' => 1500,
            'taxpayer' => 'Test LLC',
            'change' => 0,
        ],
        'refunds' => [],
        'cashier' => [
            'internalId' => 1,
            'deskId' => 'desk-1',
            'name' => [
                ['id' => 100, 'language' => 'hy', 'content' => 'Հաշվապահ'],
            ],
        ],
        'items' => [
            [
                'srcId' => 1001,
                'quantity' => 2,
                'price' => 750,
                'unit' => 'pc',
                'discount' => null,
                'discountType' => null,
                'additionalDiscount' => null,
                'additionalDiscountType' => null,
                'department' => [
                    'internalId' => 5,
                    'taxRegime' => 'vat',
                    'title' => [
                        ['id' => 200, 'language' => 'hy', 'content' => 'Բաժին'],
                    ],
                ],
                'offer' => [
                    'type' => 'product',
                    'classifierCode' => '01.01.01',
                    'title' => [
                        ['id' => 300, 'language' => 'hy', 'content' => 'Հաց'],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);
}

it('parses a complete sale detail response into nested DTOs', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeSampleSaleDetailJson()));

    $sale = $client->getSale(4711);

    expect($sale)->toBeInstanceOf(SaleDetail::class)
        ->and($sale->id)->toBe(4711)
        ->and($sale->createdAt)->toBe('2026-05-02T08:30:00.000Z')
        ->and($sale->buyerTin)->toBeNull()
        ->and($sale->cashAmount)->toBe(1500.0)
        ->and($sale->refunds)->toBe([])
        ->and($sale->cashier)->toBeInstanceOf(Cashier::class)
        ->and($sale->cashier->deskId)->toBe('desk-1')
        ->and($sale->receipt)->toBeInstanceOf(Receipt::class);

    Assert::assertNotNull($sale->receipt);
    expect($sale->receipt->fiscal)->toBe('FA00012345')
        ->and($sale->receipt->time)->toBe('1746169800000')
        ->and($sale->receipt->total)->toBe(1500.0);

    Assert::assertCount(1, $sale->items);
    Assert::assertCount(1, $sale->cashier->name);

    [$item] = $sale->items;
    expect($item)->toBeInstanceOf(SaleItem::class)
        ->and($item->unit)->toBe(Unit::Piece)
        ->and($item->discount)->toBeNull()
        ->and($item->discountType)->toBeNull()
        ->and($item->department->taxRegime)->toBe(TaxRegime::Vat)
        ->and($item->offer->type)->toBe(OfferType::Product);

    [$cashierLocalization] = $sale->cashier->name;
    expect($cashierLocalization->language)->toBe(Language::Armenian)
        ->and($cashierLocalization->content)->toBe('Հաշվապահ')
        ->and($cashierLocalization->id)->toBe(100);
});

it('handles a null receipt and a refund with its own receipt', function (): void {
    [$client, $mock] = makeMockedClient();
    $body = json_encode([
        'id' => 5000,
        'createdAt' => '2026-05-02T09:00:00.000Z',
        'buyerTin' => '12345678',
        'cashAmount' => 0,
        'nonCashAmount' => 1000,
        'prepaymentAmount' => 0,
        'compensationAmount' => 0,
        'receipt' => null,
        'refunds' => [
            [
                'nonCashAmount' => 200,
                'cashAmount' => 0,
                'receipt' => null,
                'items' => [['quantity' => 1]],
            ],
        ],
        'cashier' => [
            'internalId' => 2,
            'deskId' => 'desk-2',
            'name' => [],
        ],
        'items' => [],
    ], JSON_THROW_ON_ERROR);

    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], $body));

    $sale = $client->getSale(5000);

    expect($sale->receipt)->toBeNull()
        ->and($sale->buyerTin)->toBe('12345678');

    Assert::assertCount(1, $sale->refunds);
    [$refund] = $sale->refunds;

    expect($refund)->toBeInstanceOf(SaleRefund::class)
        ->and($refund->nonCashAmount)->toBe(200.0)
        ->and($refund->receipt)->toBeNull();

    Assert::assertCount(1, $refund->items);
    [$refundItem] = $refund->items;
    expect($refundItem->quantity)->toBe(1.0);
});

it('sends a GET request to /sales/{id} with the bearer token', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], makeSampleSaleDetailJson()));

    $client->getSale(4711);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/sales/4711')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key');
});

it('rejects a negative saleId', function (): void {
    [$client] = makeMockedClient();

    $client->getSale(-1);
})->throws(InvalidArgumentException::class, 'saleId must be non-negative.');

it('throws VcrApiException on HTTP 404', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(404, ['Content-Type' => 'application/json'], '{"code":"NOT_FOUND","message":"Sale 4711 not found"}'));

    try {
        $client->getSale(4711);
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(404)
            ->and($e->apiErrorCode)->toBe('NOT_FOUND');
    }
});
