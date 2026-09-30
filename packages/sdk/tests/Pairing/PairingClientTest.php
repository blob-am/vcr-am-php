<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Input\RegisterPairingRequestInput;
use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use BlobSolutions\VcrAm\VcrClient;
use InvalidArgumentException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\RequestInterface;

function pairingInput(): RegisterPairingRequestInput
{
    return new RegisterPairingRequestInput(
        redirectUri: 'https://shop.example/wp-admin/admin.php?page=vcr-am',
        storeName: 'Example Shop',
        state: 'a-persisted-nonce',
        codeChallenge: CodeVerifier::challengeFor(CodeVerifier::generate()),
    );
}

function registeredResponse(): Response
{
    return new Response(201, ['Content-Type' => 'application/json'], json_encode([
        'requestId' => 'req_abc123',
        'connectUrl' => 'https://vcr.am/connect?request=req_abc123',
        'expiresAt' => '2026-09-30T12:00:00.000Z',
    ], JSON_THROW_ON_ERROR));
}

function exchangedResponse(?string $crn = '1234567890123'): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'apiKey' => 'vcr_live_secret_value',
        'expiresAt' => '2028-09-30T12:00:00.000Z',
        'vcrId' => 42,
        'crn' => $crn,
        'registerName' => 'My Shop',
    ], JSON_THROW_ON_ERROR));
}

it('registers a request without an API key', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(registeredResponse());

    $client->registerRequest(pairingInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    // The absent header is the point of this client: a store being paired has
    // no key yet, and sending an empty one would look like a revoked key.
    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://vcr.am/api/v1/connect/requests')
        ->and($request->hasHeader('X-API-Key'))->toBeFalse();
});

it('sends every field the endpoint needs, and nothing else', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(registeredResponse());

    $input = pairingInput();
    $client->registerRequest($input);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    $body = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

    expect($body)->toBe([
        'redirectUri' => 'https://shop.example/wp-admin/admin.php?page=vcr-am',
        'storeName' => 'Example Shop',
        'state' => 'a-persisted-nonce',
        'codeChallenge' => $input->codeChallenge,
    ]);
});

it('never sends the verifier when registering', function (): void {
    // Registering the challenge and keeping the verifier is the entire
    // mechanism; shipping both would make PKCE decorative.
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(registeredResponse());

    $verifier = CodeVerifier::generate();
    $client->registerRequest(new RegisterPairingRequestInput(
        redirectUri: 'https://shop.example/callback',
        storeName: 'Example Shop',
        state: 'nonce',
        codeChallenge: CodeVerifier::challengeFor($verifier),
    ));

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect((string) $request->getBody())->not->toContain($verifier);
});

it('parses the registered request', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(registeredResponse());

    $registered = $client->registerRequest(pairingInput());

    expect($registered->requestId)->toBe('req_abc123')
        ->and($registered->connectUrl)->toBe('https://vcr.am/connect?request=req_abc123')
        ->and($registered->expiresAt)->toBe('2026-09-30T12:00:00.000Z');
});

it('surfaces the per-field message when a request is refused', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], json_encode([
        'error' => 'redirectUri rejected (insecure_scheme): it must be an absolute https URL.',
    ], JSON_THROW_ON_ERROR)));

    try {
        $client->registerRequest(pairingInput());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->apiErrorMessage)->toContain('insecure_scheme');
    }
});

it('exchanges a code for a key', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(exchangedResponse());

    $verifier = CodeVerifier::generate();
    $paired = $client->exchangeCode('the-code', $verifier);

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect((string) $request->getUri())->toBe('https://vcr.am/api/v1/connect/exchange')
        ->and(json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(['code' => 'the-code', 'codeVerifier' => $verifier]);

    expect($paired->apiKey)->toBe('vcr_live_secret_value')
        ->and($paired->vcrId)->toBe(42)
        ->and($paired->crn)->toBe('1234567890123')
        ->and($paired->registerName)->toBe('My Shop');
});

it('parses a register that has not activated yet', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(exchangedResponse(crn: null));

    $paired = $client->exchangeCode('the-code', CodeVerifier::generate());

    expect($paired->crn)->toBeNull();
});

it('refuses a malformed verifier without spending the code', function (): void {
    // The code is single-use. Letting a typo'd verifier reach the server
    // would burn it against a request the store can never complete.
    [$client, $mock] = makeMockedPairingClient();

    expect(fn (): object => $client->exchangeCode('the-code', 'too-short'))
        ->toThrow(InvalidArgumentException::class);

    expect($mock->getRequests())->toHaveCount(0);
});

it('tells every exchange failure apart from none of them', function (): void {
    // The server answers unknown, spent, expired and wrong-verifier with one
    // identical message so a stolen code cannot be probed for liveness. The
    // SDK must pass that through rather than inventing a taxonomy.
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(new Response(400, ['Content-Type' => 'application/json'], json_encode([
        'error' => 'That code cannot be exchanged.',
    ], JSON_THROW_ON_ERROR)));

    try {
        $client->exchangeCode('stolen-code', CodeVerifier::generate());
        Assert::fail('expected VcrApiException');
    } catch (VcrApiException $e) {
        expect($e->statusCode)->toBe(400)
            ->and($e->apiErrorMessage)->toBe('That code cannot be exchanged.');
    }
});

it('rejects a response missing the key it was called for', function (): void {
    [$client, $mock] = makeMockedPairingClient();
    $mock->addResponse(new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'expiresAt' => '2028-09-30T12:00:00.000Z',
        'vcrId' => 42,
        'crn' => null,
        'registerName' => 'My Shop',
    ], JSON_THROW_ON_ERROR)));

    $client->exchangeCode('the-code', CodeVerifier::generate());
})->throws(VcrValidationException::class);

it('announces the SDK and the store software in the User-Agent', function (): void {
    [$client, $mock] = makeMockedPairingClient('my-store/1.0 (WordPress/7.1)');
    $mock->addResponse(registeredResponse());

    $client->registerRequest(pairingInput());

    $request = $mock->getLastRequest();
    assert($request instanceof RequestInterface);

    expect($request->getHeaderLine('User-Agent'))
        ->toStartWith('vcr-am-sdk-php/' . VcrClient::VERSION)
        ->and($request->getHeaderLine('User-Agent'))
        ->toEndWith(' my-store/1.0 (WordPress/7.1)');
});

it('refuses each empty field at the call site', function (): void {
    expect(fn (): object => new RegisterPairingRequestInput('', 'Shop', 'nonce', 'challenge'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => new RegisterPairingRequestInput('https://shop.example', '  ', 'nonce', 'challenge'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => new RegisterPairingRequestInput('https://shop.example', 'Shop', '', 'challenge'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => new RegisterPairingRequestInput('https://shop.example', 'Shop', 'nonce', ''))
        ->toThrow(InvalidArgumentException::class);
});
