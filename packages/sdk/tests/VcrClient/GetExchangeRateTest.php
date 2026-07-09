<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Model\ExchangeRate;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('GETs /exchange-rate with the currency query and parses the rate', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'currency' => 'RUB',
        'ratePerUnit' => 4.32,
        'amount' => 1,
        'rateDate' => '2026-07-08',
        'saleDate' => '2026-07-09',
        'ruleVersion' => 'HO-234-N',
        'source' => 'CBA',
    ], JSON_THROW_ON_ERROR)));

    $rate = $client->getExchangeRate('RUB');

    expect($rate)->toBeInstanceOf(ExchangeRate::class)
        ->and($rate->currency)->toBe('RUB')
        ->and($rate->ratePerUnit)->toBe(4.32)
        ->and($rate->amount)->toBe(1)
        ->and($rate->rateDate)->toBe('2026-07-08')
        ->and($rate->source)->toBe('CBA');

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/exchange-rate?currency=RUB');
});

it('rejects a currency that is not a 3-letter code before making a request', function (): void {
    [$client] = makeMockedClient();

    $client->getExchangeRate('AMDX');
})->throws(InvalidArgumentException::class, 'currency must be a 3-letter ISO 4217 code');

it('surfaces a server rejection (e.g. AMD) as VcrApiException', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], json_encode([
        'error' => 'AMD is the native receipt currency and needs no conversion',
    ], JSON_THROW_ON_ERROR)));

    try {
        $client->getExchangeRate('AMD');
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400);
    }
});
