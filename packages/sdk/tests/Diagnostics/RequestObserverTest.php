<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Diagnostics\RequestAttempt;
use BlobSolutions\VcrAm\Diagnostics\RequestObserver;
use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterPairingRequestInput;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use BlobSolutions\VcrAm\PairingClient;
use BlobSolutions\VcrAm\Unit;
use BlobSolutions\VcrAm\VcrClient;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Log\AbstractLogger;

const TRACE_ID = 'f05e07ec81806aa151500c414d14d53b';

final class RecordingObserver implements RequestObserver
{
    /** @var list<RequestAttempt> */
    public array $attempts = [];

    public function onRequestAttempt(RequestAttempt $attempt): void
    {
        $this->attempts[] = $attempt;
    }
}

/**
 * @return array{0: VcrClient, 1: MockClient, 2: RecordingObserver}
 */
function makeObservedClient(?RequestObserver $observer = null, ?AbstractLogger $logger = null): array
{
    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $recording = new RecordingObserver();
    $client = new VcrClient(
        apiKey: 'test-key',
        httpClient: $mockClient,
        requestFactory: $factory,
        streamFactory: $factory,
        logger: $logger,
        requestObserver: $observer ?? $recording,
    );

    return [$client, $mockClient, $recording];
}

function observedSaleInput(): RegisterSaleInput
{
    return new RegisterSaleInput(
        cashier: CashierId::byDeskId('desk-1'),
        items: [
            new SaleItem(
                offer: Offer::existing('sku-bread'),
                department: new Department(5),
                quantity: '1',
                price: '750',
                unit: Unit::Piece,
            ),
        ],
        amount: new SaleAmount(cash: '750'),
        buyer: Buyer::individual(),
    );
}

function onlyAttempt(RecordingObserver $observer): RequestAttempt
{
    Assert::assertCount(1, $observer->attempts);
    [$attempt] = $observer->attempts;

    return $attempt;
}

it('reports a successful call with the trace id the server answered with', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(200, ['VCR-Trace-Id' => TRACE_ID], '[]'));

    $client->listCashiers();

    $attempt = onlyAttempt($observer);
    expect($attempt->method)->toBe('GET')
        ->and($attempt->path)->toBe('/cashiers')
        ->and($attempt->statusCode)->toBe(200)
        ->and($attempt->traceId)->toBe(TRACE_ID)
        ->and($attempt->idempotencyKey)->toBeNull()
        ->and($attempt->failure)->toBeNull()
        ->and($attempt->succeeded())->toBeTrue()
        ->and($attempt->durationMs)->toBeGreaterThanOrEqual(0);
});

it('has no trace id when the answer carries none', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(200, [], '[]'));

    $client->listCashiers();

    expect(onlyAttempt($observer)->traceId)->toBeNull();
});

it('keeps the query string out of the path', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(200, [], '[]'));

    $client->listPrepayments(customerRef: 'buyer@example.com');

    expect(onlyAttempt($observer)->path)->toBe('/prepayments');
});

it('reports a refused call with the status and the API message, then throws', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(
        401,
        ['VCR-Trace-Id' => TRACE_ID],
        '{"error":"Invalid API key"}',
    ));

    expect(fn () => $client->listCashiers())->toThrow(VcrApiException::class);

    $attempt = onlyAttempt($observer);
    expect($attempt->statusCode)->toBe(401)
        ->and($attempt->traceId)->toBe(TRACE_ID)
        ->and($attempt->failure)->toBe('Invalid API key')
        ->and($attempt->succeeded())->toBeFalse();
});

it('names the status when the error body is not a VCR envelope', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(502, [], '<html>Bad Gateway</html>'));

    expect(fn () => $client->listCashiers())->toThrow(VcrApiException::class);

    $attempt = onlyAttempt($observer);
    expect($attempt->statusCode)->toBe(502)
        ->and($attempt->traceId)->toBeNull()
        ->and($attempt->failure)->toBe('HTTP 502');
});

it('reports a call that got no response, without the query string in the reason', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $factory = new Psr17Factory();
    $url = 'https://vcr.am/api/v1/prepayments?customerRef=buyer%40example.com';
    $mock->addException(new NetworkException(
        'cURL error 28: timed out for ' . $url,
        $factory->createRequest('GET', $url),
    ));

    expect(fn () => $client->listPrepayments(customerRef: 'buyer@example.com'))
        ->toThrow(VcrNetworkException::class);

    $attempt = onlyAttempt($observer);
    expect($attempt->statusCode)->toBeNull()
        ->and($attempt->traceId)->toBeNull()
        ->and($attempt->failure)->toContain('cURL error 28: timed out for /prepayments')
        ->and($attempt->failure)->not->toContain('buyer');
});

it('reports a 2xx whose body does not fit as a failure', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(200, ['VCR-Trace-Id' => TRACE_ID], '{"unexpected":true}'));

    expect(fn () => $client->whoami())->toThrow(VcrValidationException::class);

    $attempt = onlyAttempt($observer);
    expect($attempt->statusCode)->toBe(200)
        ->and($attempt->traceId)->toBe(TRACE_ID)
        ->and($attempt->failure)->toContain('did not match the expected schema');
});

it('carries the idempotency key of the call', function (): void {
    [$client, $mock, $observer] = makeObservedClient();
    $mock->addResponse(new Response(500, [], '{"error":"boom"}'));

    expect(fn () => $client->registerSale(observedSaleInput(), 'order-8402'))
        ->toThrow(VcrApiException::class);

    expect(onlyAttempt($observer)->idempotencyKey)->toBe('order-8402');
});

it('returns the result and logs when the observer itself throws', function (): void {
    $logger = new class () extends AbstractLogger {
        /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
        }
    };
    $broken = new class () implements RequestObserver {
        public function onRequestAttempt(RequestAttempt $attempt): void
        {
            throw new RuntimeException('journal table is missing');
        }
    };
    [$client, $mock] = makeObservedClient($broken, $logger);
    $mock->addResponse(new Response(200, ['VCR-Trace-Id' => TRACE_ID], '[]'));

    expect($client->listCashiers())->toBe([]);

    $errors = array_values(array_filter($logger->records, fn (array $r): bool => $r['level'] === 'error'));
    Assert::assertCount(1, $errors);
    [$error] = $errors;
    expect($error['message'])->toBe('VCR.AM request observer failed')
        ->and($error['context'])->toMatchArray([
            'path' => '/cashiers',
            'traceId' => TRACE_ID,
            'error' => 'journal table is missing',
        ]);
});

it('observes pairing calls too', function (): void {
    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $observer = new RecordingObserver();
    $pairing = new PairingClient(
        httpClient: $mockClient,
        requestFactory: $factory,
        streamFactory: $factory,
        requestObserver: $observer,
    );
    $mockClient->addResponse(new Response(400, ['VCR-Trace-Id' => TRACE_ID], '{"error":"redirectUri is not allowed"}'));

    $verifier = CodeVerifier::generate();
    expect(fn () => $pairing->registerRequest(new RegisterPairingRequestInput(
        redirectUri: 'https://shop.example/wp-admin/admin.php?page=vcr-am',
        storeName: 'Example Shop',
        state: str_repeat('a', 32),
        codeChallenge: CodeVerifier::challengeFor($verifier),
    )))->toThrow(VcrApiException::class);

    $attempt = onlyAttempt($observer);
    expect($attempt->path)->toBe('/connect/requests')
        ->and($attempt->statusCode)->toBe(400)
        ->and($attempt->traceId)->toBe(TRACE_ID);
});
