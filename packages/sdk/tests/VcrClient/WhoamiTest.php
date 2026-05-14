<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Model\AccountBusinessEntity;
use BlobSolutions\VcrAm\Model\AccountInfo;
use BlobSolutions\VcrAm\VcrMode;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

it('sends a GET request to /whoami with the X-API-Key header', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'vcrId' => 42,
        'crn' => '1234567890123',
        'mode' => 'production',
        'tradingPlatformName' => 'My Shop',
        'businessEntity' => ['tin' => '01234567', 'name' => 'My Shop LLC'],
    ], JSON_THROW_ON_ERROR)));

    $client->whoami();

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/whoami')
        ->and($request->getHeaderLine('X-API-Key'))->toBe('test-key');
});

it('parses a production VCR identity', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'vcrId' => 42,
        'crn' => '1234567890123',
        'mode' => 'production',
        'tradingPlatformName' => 'My Shop',
        'businessEntity' => ['tin' => '01234567', 'name' => 'My Shop LLC'],
    ], JSON_THROW_ON_ERROR)));

    $info = $client->whoami();

    Assert::assertInstanceOf(AccountInfo::class, $info);
    expect($info->vcrId)->toBe(42)
        ->and($info->crn)->toBe('1234567890123')
        ->and($info->mode)->toBe(VcrMode::Production)
        ->and($info->tradingPlatformName)->toBe('My Shop');

    expect($info->businessEntity)->toBeInstanceOf(AccountBusinessEntity::class)
        ->and($info->businessEntity->tin)->toBe('01234567')
        ->and($info->businessEntity->name)->toBe('My Shop LLC');
});

it('parses a sandbox VCR with no CRN (pre-activation)', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'vcrId' => 100,
        'crn' => null,
        'mode' => 'sandbox',
        'tradingPlatformName' => 'Sandbox',
        'businessEntity' => ['tin' => '01234567', 'name' => ''],
    ], JSON_THROW_ON_ERROR)));

    $info = $client->whoami();

    expect($info->crn)->toBeNull()
        ->and($info->mode)->toBe(VcrMode::Sandbox)
        ->and($info->businessEntity->name)->toBe('');
});

it('throws VcrApiException on HTTP 401', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(401, ['Content-Type' => 'application/json'], json_encode([
        'code' => 'INVALID_TOKEN', 'message' => 'API key revoked',
    ], JSON_THROW_ON_ERROR)));

    try {
        $client->whoami();
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(401)
            ->and($e->apiErrorCode)->toBe('INVALID_TOKEN');
    }
});

it('throws VcrValidationException when mode is an unknown string', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'vcrId' => 1,
        'crn' => null,
        'mode' => 'staging',
        'tradingPlatformName' => '',
        'businessEntity' => ['tin' => '01234567', 'name' => ''],
    ], JSON_THROW_ON_ERROR)));

    $client->whoami();
})->throws(VcrValidationException::class);
