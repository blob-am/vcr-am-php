<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Facades;

use BlobSolutions\LaravelVcrAm\Testing\VcrAmFake;
use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use Closure;
use Illuminate\Support\Facades\Facade;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * @method static \BlobSolutions\VcrAm\Model\AccountInfo whoami()
 * @method static list<\BlobSolutions\VcrAm\Model\CashierListItem> listCashiers()
 * @method static \BlobSolutions\VcrAm\Model\RegisterSaleResponse registerSale(\BlobSolutions\VcrAm\Input\RegisterSaleInput $input)
 * @method static \BlobSolutions\VcrAm\Model\RegisterSaleRefundResponse registerSaleRefund(\BlobSolutions\VcrAm\Input\RegisterSaleRefundInput $input)
 * @method static \BlobSolutions\VcrAm\Model\RegisterPrepaymentResponse registerPrepayment(\BlobSolutions\VcrAm\Input\RegisterPrepaymentInput $input)
 * @method static \BlobSolutions\VcrAm\Model\PrepaymentDetail getPrepayment(int $prepaymentId)
 * @method static \BlobSolutions\VcrAm\Model\RegisterPrepaymentRefundResponse registerPrepaymentRefund(\BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateCashierResponse createCashier(\BlobSolutions\VcrAm\Input\CreateCashierInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateDepartmentResponse createDepartment(\BlobSolutions\VcrAm\Input\CreateDepartmentInput $input)
 * @method static \BlobSolutions\VcrAm\Model\CreateOfferResponse createOffer(\BlobSolutions\VcrAm\Input\CreateOfferInput $input)
 * @method static list<\BlobSolutions\VcrAm\Model\ClassifierSearchItem> searchClassifier(string $query, \BlobSolutions\VcrAm\OfferType $type, \BlobSolutions\VcrAm\Language $language)
 * @method static \BlobSolutions\VcrAm\Model\SaleDetail getSale(int $saleId)
 *
 * @see VcrClient
 */
final class VcrAm extends Facade
{
    /**
     * Holds the active fake for the current test, so assertion helpers
     * (`VcrAm::assertSent(...)`) can find it without the user threading the
     * fake instance through their tests.
     */
    private static ?VcrAmFake $fake = null;

    protected static function getFacadeAccessor(): string
    {
        return VcrClient::class;
    }

    /**
     * Returns the parallel sandbox `VcrClient`, bound at registration time
     * from `VCR_AM_SANDBOX_API_KEY`. Throws if no sandbox key is configured —
     * fails loudly so production code that calls `VcrAm::sandbox()` without
     * the env var set doesn't silently downgrade to the production client.
     */
    public static function sandbox(): VcrClient
    {
        $client = app(VcrAmServiceProvider::SANDBOX_BINDING);

        if (! $client instanceof VcrClient) {
            throw new RuntimeException(sprintf(
                'VCR.AM: expected %s under `%s`, got %s.',
                VcrClient::class,
                VcrAmServiceProvider::SANDBOX_BINDING,
                get_debug_type($client),
            ));
        }

        return $client;
    }

    /**
     * Swap both the production and the sandbox `VcrClient` bindings for
     * fakes that route every request through an in-memory recorder.
     *
     * After `fake()`, any code that resolves `VcrClient` (via the facade,
     * `app(VcrClient::class)`, or constructor DI) gets the fake, and calls
     * to it become assertable via `VcrAm::assertSent()` / `assertNotSent()`
     * etc. Tests that don't pre-register a stub for an endpoint get a loud
     * `RuntimeException` naming the missing matcher — chosen on purpose so
     * unexpected SDK calls surface as test failures, not silent passes.
     *
     * @param array<string, Closure(RequestInterface): ResponseInterface|array<array-key, mixed>> $stubs
     */
    public static function fake(array $stubs = []): VcrAmFake
    {
        self::$fake = new VcrAmFake(app(), $stubs);

        return self::$fake;
    }

    /**
     * Returns the active fake, or fails loudly. Mostly internal: assertion
     * helpers below use it. Tests usually keep the value returned from
     * `fake()` instead.
     */
    public static function fakeRecorder(): VcrAmFake
    {
        if (self::$fake === null) {
            throw new RuntimeException(
                'VCR.AM: no active fake. Call VcrAm::fake() before VcrAm::assertSent() / similar.',
            );
        }

        return self::$fake;
    }

    /**
     * Proxies to {@see VcrAmFake::assertSent}. Convenience for tests that
     * already write `VcrAm::registerSale(...)` and prefer the same facade
     * style for assertions.
     *
     * @param non-empty-string $matcher
     * @param ?Closure(array<array-key, mixed>|null, \BlobSolutions\LaravelVcrAm\Testing\RecordedRequest): bool $bodyMatcher
     */
    public static function assertSent(string $matcher, ?Closure $bodyMatcher = null): void
    {
        self::fakeRecorder()->assertSent($matcher, $bodyMatcher);
    }

    /**
     * Proxies to {@see VcrAmFake::assertNotSent}.
     *
     * @param non-empty-string $matcher
     * @param ?Closure(array<array-key, mixed>|null, \BlobSolutions\LaravelVcrAm\Testing\RecordedRequest): bool $bodyMatcher
     */
    public static function assertNotSent(string $matcher, ?Closure $bodyMatcher = null): void
    {
        self::fakeRecorder()->assertNotSent($matcher, $bodyMatcher);
    }

    /**
     * Proxies to {@see VcrAmFake::assertNothingSent}.
     */
    public static function assertNothingSent(): void
    {
        self::fakeRecorder()->assertNothingSent();
    }

    /**
     * Proxies to {@see VcrAmFake::assertSentCount}.
     */
    public static function assertSentCount(int $count): void
    {
        self::fakeRecorder()->assertSentCount($count);
    }

    /**
     * Called automatically by Laravel between tests when the package's
     * `tests/TestCase.php` extends `Orchestra\Testbench\TestCase`. Hand-roll
     * a call in your own `tearDown` if you skip Testbench.
     */
    public static function clearResolvedInstances(): void
    {
        parent::clearResolvedInstances();

        self::$fake = null;
    }
}
