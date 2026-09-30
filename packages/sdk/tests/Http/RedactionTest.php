<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Input\CreateCashierInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\Input\RegisterPairingRequestInput;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use Http\Client\Exception\NetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

/**
 * What an exception from this SDK is allowed to carry.
 *
 * Every exception here is handed the request that produced it, and APMs
 * (Sentry, Bugsnag, Laravel's own handler) serialise exception state — headers
 * and bodies alike. The header half was always stripped; the body half was not,
 * and pairing put two credentials in a body for the first time.
 */
function networkFailure(): NetworkException
{
    $factory = new Psr17Factory();

    return new NetworkException(
        'Connection reset by peer',
        $factory->createRequest('POST', 'https://vcr.am/api/v1/connect/exchange'),
    );
}

const A_VERIFIER = 'Zm9vYmFyLXZlcmlmaWVyLXdpdGgtZW5vdWdoLWxlbmd0aA';

it('keeps both halves of a pairing exchange out of a network exception', function (): void {
    // The case that matters: a socket failure at the exchange leaves the code
    // possibly unspent, and it mints a register-wide key for two years.
    [$pairing, $mock] = makeMockedPairingClient();
    $mock->addException(networkFailure());

    try {
        $pairing->exchangeCode('a-live-single-use-code', A_VERIFIER);
        expect(false)->toBeTrue();
    } catch (VcrNetworkException $e) {
        $body = (string) $e->request->getBody();

        expect($body)->not->toContain('a-live-single-use-code')
            ->and($body)->not->toContain(A_VERIFIER)
            ->and($body)->toContain('[REDACTED]');
    }
});

it('keeps the cashier PIN out of a network exception', function (): void {
    // __debugInfo() on the input keeps this out of a var_dump. It does nothing
    // for the serialized body, which is what travels on the exception.
    [$client, $mock] = makeMockedClient();
    $mock->addException(networkFailure());

    try {
        $client->createCashier(new CreateCashierInput(
            name: new LocalizedName(
                value: ['hy' => 'Աննա'],
                localizationStrategy: LocalizationStrategy::Transliteration,
            ),
            password: '4821',
        ));
        expect(false)->toBeTrue();
    } catch (VcrNetworkException $e) {
        $body = (string) $e->request->getBody();

        expect($body)->not->toContain('4821')
            ->and($body)->toContain('[REDACTED]');
    }
});

it('still strips the API key header', function (): void {
    [$client, $mock] = makeMockedClient();
    $mock->addException(networkFailure());

    try {
        $client->listCashiers();
        expect(false)->toBeTrue();
    } catch (VcrNetworkException $e) {
        expect($e->request->hasHeader('X-API-Key'))->toBeFalse();
    }
});

it('leaves a business body untouched', function (): void {
    // The request body on an exception is how a refused receipt gets
    // diagnosed. A redactor that ate `classifierCode` would cost more than it
    // protects.
    [$pairing, $mock] = makeMockedPairingClient();
    $mock->addException(networkFailure());

    try {
        $pairing->registerRequest(new RegisterPairingRequestInput(
            redirectUri: 'https://shop.example/wp-admin/admin.php?page=wc-settings&tab=vcr',
            storeName: 'Corner Shop',
            state: 'state-token-1234',
            codeChallenge: CodeVerifier::challengeFor(A_VERIFIER),
        ));
        expect(false)->toBeTrue();
    } catch (VcrNetworkException $e) {
        $body = (string) $e->request->getBody();

        expect($body)->toContain('Corner Shop')
            ->and($body)->toContain('state-token-1234')
            ->and($body)->toContain(CodeVerifier::challengeFor(A_VERIFIER))
            ->and($body)->not->toContain('[REDACTED]');
    }
});

it('keeps a minted key out of a response that failed to map', function (): void {
    // A 200 whose shape has drifted is the one way a body containing a key
    // reaches exception state, and that key is never shown again.
    [$pairing, $mock] = makeMockedPairingClient();
    $mock->addResponse(new Response(
        200,
        ['Content-Type' => 'application/json'],
        json_encode([
            'apiKey' => 'vcr_live_the_only_copy',
            'expiresAt' => '2028-09-30T00:00:00.000Z',
            'vcrId' => 'not-an-integer',
            'crn' => '77012345',
            'registerName' => 'Corner Shop',
        ], JSON_THROW_ON_ERROR),
    ));

    try {
        $pairing->exchangeCode('a-code', A_VERIFIER);
        expect(false)->toBeTrue();
    } catch (VcrValidationException $e) {
        expect($e->rawBody)->not->toContain('vcr_live_the_only_copy')
            ->and($e->rawBody)->toContain('[REDACTED]')
            // Still diagnosable: the field that actually broke is readable.
            ->and($e->rawBody)->toContain('not-an-integer');
    }
});

it('leaves an SRC error code readable on a refused request', function (): void {
    // `code` is a credential on the way out and a diagnosis on the way back.
    // Redacting it in both directions would blind the field a rejected receipt
    // is read from.
    [$client, $mock] = makeMockedClient();
    $mock->addResponse(new Response(
        400,
        ['Content-Type' => 'application/problem+json'],
        json_encode([
            'error' => 'The tax authority refused the receipt.',
            'issues' => [['code' => 196, 'message' => 'INACTIVE_CRN']],
        ], JSON_THROW_ON_ERROR),
    ));

    try {
        $client->listCashiers();
        expect(false)->toBeTrue();
    } catch (VcrApiException $e) {
        expect($e->rawBody)->toContain('196')
            ->and($e->rawBody)->not->toContain('[REDACTED]');
    }
});
