<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\Facades\VcrAm;
use BlobSolutions\LaravelVcrAm\Testing\FakeHttpClient;
use BlobSolutions\LaravelVcrAm\Testing\RecordedRequest;
use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;
use BlobSolutions\VcrAm\VcrClient;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    // Each test gets a fresh fake. Required because the facade caches the
    // instance behind `VcrAm::fake()` and we want every test to start clean.
    VcrAm::clearResolvedInstances();
});

function sampleSale(): RegisterSaleInput
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

it('replaces the VcrClient binding with a fake that records calls', function (): void {
    $fake = VcrAm::fake([
        'POST /sales' => [
            'urlId' => 'URL-1',
            'saleId' => 1,
            'crn' => '1234567890123',
            'srcReceiptId' => 100,
            'fiscal' => 'F-1',
        ],
    ]);

    $client = app(VcrClient::class);
    $response = $client->registerSale(sampleSale());

    expect($response->saleId)->toBe(1)
        ->and($response->fiscal)->toBe('F-1');

    $recorded = $fake->recorded();
    expect($recorded)->toHaveCount(1);
    expect($recorded[0])->toBeInstanceOf(RecordedRequest::class);
    expect($recorded[0]->method)->toBe('POST');
    expect($recorded[0]->path)->toBe('/sales');
});

it('strips the /api/v1 base from the path so stubs read like SDK endpoints', function (): void {
    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrClient::class)->registerSale(sampleSale());

    VcrAm::assertSent('POST /sales');
});

it('exposes the same recorder for the production and sandbox clients', function (): void {
    config()->set('vcr-am.sandbox_api_key', 'sandbox-key');

    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrAmServiceProvider::SANDBOX_BINDING)->registerSale(sampleSale());

    VcrAm::assertSentCount(1);
    VcrAm::assertSent('POST /sales');
});

it('passes the decoded body to the body matcher', function (): void {
    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrClient::class)->registerSale(sampleSale());

    VcrAm::assertSent(
        'POST /sales',
        fn (?array $body): bool => is_array($body)
            && isset($body['cashier']['deskId'])
            && $body['cashier']['deskId'] === 'desk-1'
            && count($body['items']) === 1,
    );
});

it('lets a closure stub vary the response by request', function (): void {
    VcrAm::fake([
        'POST /sales' => fn () => FakeHttpClient::jsonResponse([
            'urlId' => 'dyn',
            'saleId' => 99,
            'crn' => 'C',
            'srcReceiptId' => 1,
            'fiscal' => 'F',
        ], 200),
    ]);

    $response = app(VcrClient::class)->registerSale(sampleSale());

    expect($response->saleId)->toBe(99);
});

it('throws on an unstubbed call, naming the missing matcher', function (): void {
    VcrAm::fake();

    try {
        app(VcrClient::class)->registerSale(sampleSale());
        $this->fail('expected RuntimeException');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('POST /sales');
    }
});

it('assertNothingSent passes on a clean fake', function (): void {
    VcrAm::fake();

    VcrAm::assertNothingSent();
});

it('assertNothingSent fails after a recorded call', function (): void {
    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrClient::class)->registerSale(sampleSale());

    expect(fn () => VcrAm::assertNothingSent())->toThrow(AssertionFailedError::class);
});

it('assertNotSent passes when nothing matches', function (): void {
    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrClient::class)->registerSale(sampleSale());

    VcrAm::assertNotSent('POST /prepayments');
});

it('assertSent fails with a helpful diff when no requests matched', function (): void {
    VcrAm::fake([
        'POST /sales' => ['urlId' => 'X', 'saleId' => 1, 'crn' => 'C', 'srcReceiptId' => 1, 'fiscal' => 'F'],
    ]);

    app(VcrClient::class)->registerSale(sampleSale());

    try {
        VcrAm::assertSent('POST /prepayments');
        $this->fail('expected AssertionFailedError');
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())->toContain('POST /sales');
    }
});

it('fakeRecorder() throws when fake() was not called', function (): void {
    VcrAm::clearResolvedInstances();

    VcrAm::assertNothingSent();
})->throws(RuntimeException::class, 'no active fake');
