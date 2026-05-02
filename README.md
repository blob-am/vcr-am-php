# VCR.AM PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/blob-solutions/vcr-am-sdk.svg)](https://packagist.org/packages/blob-solutions/vcr-am-sdk)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/blob-solutions/vcr-am-sdk/php)](https://packagist.org/packages/blob-solutions/vcr-am-sdk)
[![License](https://img.shields.io/packagist/l/blob-solutions/vcr-am-sdk.svg)](LICENSE)
[![CI](https://github.com/blob-am/vcr-am-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/blob-am/vcr-am-sdk-php/actions/workflows/ci.yml)

Official PHP SDK for the [VCR.AM](https://vcr.am) Virtual Cash Register API. Fiscalize sales, refunds, and prepayments through Armenia's State Revenue Committee — without touching XML, PSR-7, or wire-format quirks.

A native sibling to the [TypeScript SDK](https://github.com/blob-am/vcr-am-sdk). Same endpoints, same error semantics, same response validation philosophy — adapted to idiomatic PHP 8.2+.

## Status

> **Pre-release.** API is being developed in lockstep with the TypeScript SDK. While the package is on `0.x`, every minor release may introduce breaking changes — pin tightly until `1.0`. See [CHANGELOG.md](CHANGELOG.md) for release notes.

> ⚠️ **Do not use v0.1.0.** The very first release shipped with the wrong auth header and is rejected by every production request. Pin `^0.1.1` or later.

## Requirements

- PHP **8.2 or newer** (8.1 hit security EOL on 2025-12-31)
- Composer 2.x
- A VCR.AM account and API key — sign up at [vcr.am](https://vcr.am)

## Installation

```bash
composer require blob-solutions/vcr-am-sdk
```

The package ships with sensible defaults (Guzzle 7 as the PSR-18 HTTP client, `nyholm/psr7` as the PSR-7/PSR-17 implementation). If your application already uses different implementations, the SDK will discover and reuse them via `php-http/discovery`.

## Quick start

Register a sale of two loaves of bread, paid in cash:

```php
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Unit;
use BlobSolutions\VcrAm\VcrClient;

$client = new VcrClient(apiKey: $_ENV['VCR_AM_API_KEY']);

$response = $client->registerSale(new RegisterSaleInput(
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
));

echo "Sale {$response->saleId} fiscalised as {$response->fiscal}\n";
echo "Public receipt: https://vcr.am/{$response->urlId}\n";
```

Every input DTO is constructor-validated, so malformed payloads fail at the call site — not after a round-trip to the SRC. All decimal monetary values are passed as strings to preserve precision over the wire (see [Monetary precision](#monetary-precision) below).

## Endpoints

### `listCashiers(): list<CashierListItem>`

Returns every cashier registered for the account, with a per-language map of names.

```php
$cashiers = $client->listCashiers();

foreach ($cashiers as $cashier) {
    $hyName = $cashier->name['hy']->content ?? '(no Armenian name)';
    echo "{$cashier->deskId} (#{$cashier->internalId}): {$hyName}\n";
}
```

### `createCashier(CreateCashierInput $input): CreateCashierResponse`

Provisions a new cashier under the account. The `password` is the terminal PIN (4–8 digits) — held in a private property and redacted from `var_dump` / `print_r`.

```php
use BlobSolutions\VcrAm\Input\CreateCashierInput;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;

$response = $client->createCashier(new CreateCashierInput(
    name: new LocalizedName(
        value: ['hy' => 'Աննա Գրիգորյան', 'en' => 'Anna Grigoryan'],
        localizationStrategy: LocalizationStrategy::Transliteration,
    ),
    password: '1234',
));

echo "Cashier #{$response->id} provisioned (deskId={$response->deskId})\n";
```

### `createDepartment(CreateDepartmentInput $input): CreateDepartmentResponse`

Creates a department (tax-regime-bound bucket of offers).

```php
use BlobSolutions\VcrAm\Input\CreateDepartmentInput;
use BlobSolutions\VcrAm\TaxRegime;

$response = $client->createDepartment(new CreateDepartmentInput(
    taxRegime: TaxRegime::Vat,
    externalId: 'erp-dept-bakery',
));

echo "Department #{$response->department} created — {$response->message}\n";
```

### `createOffer(CreateOfferInput $input): CreateOfferResponse`

Adds a standalone offer (product or service) to the account catalogue.

```php
use BlobSolutions\VcrAm\Input\CreateOfferInput;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\LocalizedName;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;

$response = $client->createOffer(new CreateOfferInput(
    type: OfferType::Product,
    classifierCode: '01.01.01',
    title: new LocalizedName(
        value: ['hy' => 'Հաց', 'en' => 'Bread'],
        localizationStrategy: LocalizationStrategy::Translation,
    ),
    defaultMeasureUnit: Unit::Piece,
    defaultDepartment: new Department(5),
    externalId: 'sku-bread',
));

echo "Offer #{$response->offerId} created\n";
```

### `searchClassifier(string $query, OfferType $type, Language $language): list<ClassifierSearchItem>`

Fuzzy-searches the SRC classifier taxonomy. Useful for populating an autocomplete in an offer-creation UI. `Language::Multi` is rejected — pick a concrete language.

```php
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\OfferType;

$matches = $client->searchClassifier('bread', OfferType::Product, Language::English);

foreach ($matches as $match) {
    echo "{$match->code} — {$match->title}\n";
}
```

### `registerSale(RegisterSaleInput $input): RegisterSaleResponse`

Fiscalizes a sale. See [Quick start](#quick-start) above for the basic shape. A more elaborate example with a business buyer, an inline new offer, and an emailed receipt:

```php
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\Department;
use BlobSolutions\VcrAm\Input\Offer;
use BlobSolutions\VcrAm\Input\OfferTitle;
use BlobSolutions\VcrAm\Input\RegisterSaleInput;
use BlobSolutions\VcrAm\Input\SaleAmount;
use BlobSolutions\VcrAm\Input\SaleItem;
use BlobSolutions\VcrAm\Input\SendReceiptToBuyer;
use BlobSolutions\VcrAm\Language;
use BlobSolutions\VcrAm\LocalizationStrategy;
use BlobSolutions\VcrAm\OfferType;
use BlobSolutions\VcrAm\Unit;

$response = $client->registerSale(new RegisterSaleInput(
    cashier: CashierId::byInternalId(42),
    items: [
        new SaleItem(
            offer: Offer::createNew(
                externalId: 'consult-1h',
                title: OfferTitle::localized(
                    content: ['hy' => 'Խորհրդատվություն, 1 ժամ', 'en' => 'Consultation, 1 hour'],
                    localizationStrategy: LocalizationStrategy::Translation,
                ),
                type: OfferType::Service,
                classifierCode: '70.22.11',
                defaultMeasureUnit: Unit::Hour,
                defaultDepartment: new Department(2),
            ),
            department: new Department(2),
            quantity: '1',
            price: '50000',
            unit: Unit::Hour,
        ),
    ],
    amount: new SaleAmount(nonCash: '50000'),
    buyer: Buyer::businessEntity(
        '01234567',
        new SendReceiptToBuyer('billing@example.am', Language::Armenian),
    ),
));
```

### `getSale(int $saleId): SaleDetail`

Reads back a previously-registered sale, including all items, refunds, and the cashier snapshot.

```php
$sale = $client->getSale(4711);

echo "Sale #{$sale->id} — total cash {$sale->cashAmount}\n";

foreach ($sale->items as $item) {
    echo "  - srcId={$item->srcId} qty={$item->quantity} @ {$item->price}\n";
}

foreach ($sale->refunds as $refund) {
    echo "  refund: cash={$refund->cashAmount}\n";
}
```

### `registerSaleRefund(RegisterSaleRefundInput $input): RegisterSaleRefundResponse`

Refunds a previously-registered sale — full or partial.

**Full refund** (omit `items`):

```php
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RegisterSaleRefundInput;
use BlobSolutions\VcrAm\RefundReason;

$response = $client->registerSaleRefund(new RegisterSaleRefundInput(
    cashier: CashierId::byDeskId('desk-1'),
    saleId: 4711,
    reason: RefundReason::CustomerRequest,
));
```

**Partial refund** by `srcId` of a specific sale item:

```php
use BlobSolutions\VcrAm\Input\RefundAmount;
use BlobSolutions\VcrAm\Input\RefundItemInput;

$response = $client->registerSaleRefund(new RegisterSaleRefundInput(
    cashier: CashierId::byDeskId('desk-1'),
    saleId: 4711,
    reason: RefundReason::DefectiveGoods,
    reasonNote: 'Mouldy crust on one loaf',
    refundAmounts: new RefundAmount(cash: '750'),
    items: [
        new RefundItemInput(srcId: 999_001, quantity: '1'),
    ],
));
```

### `registerPrepayment(RegisterPrepaymentInput $input): RegisterPrepaymentResponse`

Registers an advance payment from a buyer that will be redeemed against a future sale. Has no items — a prepayment is a single sum, not a basket.

```php
use BlobSolutions\VcrAm\Input\Buyer;
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\PrepaymentAmount;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentInput;

$response = $client->registerPrepayment(new RegisterPrepaymentInput(
    cashier: CashierId::byDeskId('desk-1'),
    amount: new PrepaymentAmount(nonCash: '20000'),
    buyer: Buyer::individual(),
));

echo "Prepayment #{$response->prepaymentId}, fiscal={$response->fiscal}\n";
```

`crn` and `fiscal` may be `null` if SRC fiscal issuance is pending — the prepayment is recorded either way and the SRC handshake retries asynchronously.

### `getPrepayment(int $prepaymentId): PrepaymentDetail`

Reads back a registered prepayment, including its (at most one) refund.

```php
$prepayment = $client->getPrepayment(9001);

echo "Prepayment #{$prepayment->id} — cash={$prepayment->cashAmount}\n";

if ($prepayment->refund !== null) {
    echo "  refunded: cash={$prepayment->refund->cashAmount}\n";
}
```

### `registerPrepaymentRefund(RegisterPrepaymentRefundInput $input): RegisterPrepaymentRefundResponse`

Refunds a prepayment in full. There is no partial-refund variant — a prepayment is indivisible.

```php
use BlobSolutions\VcrAm\Input\CashierId;
use BlobSolutions\VcrAm\Input\RegisterPrepaymentRefundInput;
use BlobSolutions\VcrAm\RefundReason;

$response = $client->registerPrepaymentRefund(new RegisterPrepaymentRefundInput(
    cashier: CashierId::byDeskId('desk-1'),
    prepaymentId: 9001,
    reason: RefundReason::CustomerRequest,
));
```

## Error handling

Every call can raise one of three exceptions, all extending the abstract `BlobSolutions\VcrAm\Exception\VcrException`. Catch the base class to handle any SDK-level failure, or branch on the concrete subclass when you care about the cause:

```php
use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;

try {
    $response = $client->registerSale($input);
} catch (VcrApiException $e) {
    // Server returned a non-2xx HTTP response.
    // $e->statusCode       — HTTP status (int)
    // $e->apiErrorCode     — SRC error code (string|null), e.g. 'INVALID_TIN'
    // $e->apiErrorMessage  — human-readable detail (string|null)
    // $e->rawBody          — full response body, in case envelope parsing failed
    // $e->request          — PSR-7 request (X-API-Key header redacted)
    // $e->response         — PSR-7 response
    if ($e->statusCode === 422 && $e->apiErrorCode === 'INVALID_TIN') {
        // Surface a friendly message to the cashier UI
    }

    throw $e;
} catch (VcrNetworkException $e) {
    // DNS / TLS / TCP / timeout failure before any HTTP response.
    // $e->getPrevious() is the underlying PSR-18 ClientExceptionInterface.
    // $e->request — PSR-7 request (X-API-Key redacted)
    // Safe to retry after a backoff, but be careful with non-idempotent endpoints.
    throw $e;
} catch (VcrValidationException $e) {
    // Server returned 2xx but the body did not match the expected schema.
    // Indicates a server-side wire-format change or upstream proxy corruption.
    // $e->detail   — Valinor mapping error or 'response body is not valid JSON: …'
    // $e->rawBody  — full response body
    // This is never a caller-side bug. File an issue.
    throw $e;
}
```

The `X-API-Key` header is stripped from the request preserved on every exception. APMs that auto-serialize exception state (Sentry, Bugsnag, Laravel's verbose handler) will not surface the API key in their breadcrumbs.

## Configuration

```php
use BlobSolutions\VcrAm\VcrClient;

$client = new VcrClient(
    apiKey: $apiKey,
    baseUrl: VcrClient::DEFAULT_BASE_URL,  // 'https://vcr.am/api/v1'
    httpClient: $myPsr18Client,            // optional — falls back to discovery
    requestFactory: $myPsr17Factory,       // optional — falls back to discovery
    streamFactory: $myPsr17Factory,        // optional — falls back to discovery
    logger: $myPsr3Logger,                 // optional — defaults to NullLogger
);
```

### PSR-3 logger

The SDK emits structured log lines for outbound requests, network failures, and 4xx/5xx responses. Pass any PSR-3 logger to capture them:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('vcr-am');
$logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));

$client = new VcrClient(apiKey: $apiKey, logger: $logger);
```

Channels emitted:

| Level | Message | Context |
|---|---|---|
| `debug` | `VCR.AM request` | `method`, `url` |
| `warning` | `VCR.AM network failure` | `method`, `url`, `error` |
| `warning` | `VCR.AM API error` | `method`, `url`, `status`, `errorCode`, `errorMessage`, `rawBodyPreview` (≤500 bytes) |

The X-API-Key header is never logged.

### PSR-18 HTTP client

The SDK auto-discovers a PSR-18 client via `php-http/discovery` (the bundled Guzzle 7 by default). To override — most commonly, to set timeouts or attach middleware — construct your own and inject it:

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;

$stack = HandlerStack::create();
$stack->push(Middleware::retry(
    fn ($retries, $req, $res, $err) => $retries < 2 && $err !== null,
    fn ($retries) => 1000 * (2 ** $retries),
));

$http = new Guzzle([
    RequestOptions::CONNECT_TIMEOUT => 5,
    RequestOptions::TIMEOUT         => 30,
    'handler'                       => $stack,
]);

$client = new VcrClient(apiKey: $apiKey, httpClient: $http);
```

Any PSR-18 client works — Symfony's `symfony/http-client`, Laravel's `Illuminate\Http\Client\Factory` (with a small adapter), `php-http/curl-client`, etc.

### PSR-17 factories

The same `php-http/discovery` mechanism finds a PSR-17 request/stream factory (by default `nyholm/psr7`'s `Psr17Factory`). Override only if you need to keep a single PSR-17 implementation across your application — the SDK does not care which one it is, as long as it conforms to the standard.

## Timeouts

PSR-18 has no portable per-request timeout primitive, so the SDK does not expose one. Configure the timeout on the HTTP client you pass in (or on the auto-discovered Guzzle instance via the bundled `guzzlehttp/guzzle` dependency):

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\RequestOptions;

$http = new Guzzle([
    RequestOptions::CONNECT_TIMEOUT => 5,   // seconds
    RequestOptions::TIMEOUT         => 30,  // seconds, total
]);

$client = new VcrClient(apiKey: $apiKey, httpClient: $http);
```

A bounded timeout is strongly recommended for fiscalization endpoints — an indefinite hang on a stalled SRC backend will block the calling thread.

## Monetary precision

Decimal fields on **request** types (`SaleAmount::$cash`, `SaleItem::$price`, etc.) are typed as `string` to preserve full precision over the wire. Only digits and at most one decimal point are accepted — no sign, no scientific notation. Constructor-time validation enforces the shape.

Decimal fields on **response** types (`SaleDetail::$cashAmount`, `Receipt::$total`, etc.) are typed as PHP `float` because the API serialises them as JSON numbers (Prisma `Float` columns). This mirrors the TypeScript SDK's `z.number()` shape. **Do not use these floats for reconciliation arithmetic** — re-fetch the receipt or compute against your own ledger of decimal-strings sent in. Equality comparisons on these floats are unsafe.

## Idempotency and retries

The SDK does **not** retry failed requests automatically. Fiscalization endpoints are not guaranteed to be idempotent on the server side; a silent retry could double-register a sale.

If you need retries, attach a Guzzle (or other PSR-18) middleware that retries only on `VcrNetworkException`-class failures (DNS/TLS/timeout) and never on a `VcrApiException` — the latter means the server has already seen and rejected your request.

## Compatibility

| SDK version | PHP versions tested |
|---|---|
| `^0.x` | 8.2, 8.3, 8.4, 8.5 |

## Development

```bash
composer install
composer check    # format check + phpstan + tests
composer format   # apply Pint fixes
```

CI enforces `pint --test`, PHPStan max + strict + deprecation rules + phpunit extension, and a 100% line-coverage gate on PHP 8.5.

## License

ISC © Alex Kraiz, Blob Solutions
