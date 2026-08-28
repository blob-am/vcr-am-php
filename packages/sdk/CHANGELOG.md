# Changelog

All notable changes to this package will be documented in this file.

## [0.8.0] — 2026-08-28

### Changed — `SaleItem::$department` is now optional (breaking)

- **Omit `department` and the line inherits the department of the offer it references** — for an inline new offer, the `defaultDepartment` declared on it. Passing one explicitly still works and still wins, which is what you want when you deliberately sell the same SKU out of a second department.

  The field was required, so every integration had to name a department per line, and the natural thing to write is the first id. That is not a harmless default: departments carry the tax regime, every register is created with one department per regime in a fixed order, and `new Department(1)` is the VAT one on every register whether or not the merchant owes VAT. Nothing rejects the mismatch — the receipt simply prints a VAT line the merchant does not owe, and a fiscal receipt can only be refunded and reissued, never corrected. We found this in the field.

  ```diff
   new SaleItem(
       offer: Offer::existing('sku-bread'),
  -    department: new Department(5),
       quantity: '2',
       price: '750',
       unit: Unit::Piece,
   ),
  ```

  **Breaking:** PHP cannot make a middle parameter optional, so `department` moved from second position to the head of the optional tail — the new order is `offer, quantity, price, unit, department, discounts, totalAmountTolerance, emarks, currency`. Named arguments (what the README has always used, and what the constructor is designed for) are unaffected. Positional callers get a `TypeError` at the call site — a `Department` where a `string $quantity` is expected — not a silently mis-booked receipt.

  Requires the server-side change shipped alongside it; against an older deployment an omitted `department` is a `422`.

## [0.7.0] — 2026-08-28

### Fixed — error envelope was never parsed (breaking)

- **`VcrApiException::$apiErrorMessage` was `null` on every failure, in every version.** The SDK read `code` and `message` from the error body; the API has always sent `{ error, ... }`. Nothing matched, so every rejection arrived with no detail at all and callers were left reading `rawBody` by hand.

  The tests did not catch it because they fed the SDK the same invented envelope the SDK expected — `['code' => 'INVALID_TIN', 'message' => '…']` is a body the server has never produced. They have been rewritten against the real wire shape.

- **Removed `VcrApiException::$apiErrorCode`.** There is no top-level `code` in the API's envelope, so the property could only ever be `null`. Any `if ($e->apiErrorCode === 'INVALID_TIN')` in your code was dead: branch on `$e->statusCode`, on `$e->issues`, or on `$e->apiErrorMessage` instead.

- **`VcrApiException`'s constructor signature changed** — `apiErrorCode` is gone and the new envelope fields follow `$response` as optional named arguments. Only affects code that constructs the exception directly (test doubles, mostly).

### Added

- **`VcrApiException::$pending`** (`?PendingResource`) — a `502` raised because the tax service was unreachable now tells you the document **was** persisted and queued for automatic resubmission. Do not resend: that fiscalizes a second receipt, and a fiscal receipt can only be refunded, never deleted.

  ```php
  } catch (VcrApiException $e) {
      if ($e->pending !== null) {
          $queue->recordPending($e->pending->type, $e->pending->id);

          return; // nothing was lost
      }

      throw $e;
  }
  ```

  `PendingResource::$type` is a plain string rather than an enum on purpose: if the server starts queueing a type an older SDK does not know, an enum would fail to parse and take the real error message down with it.

- **`VcrApiException::$issues`** (`list<ApiErrorIssue>`) — field-level complaints from a request that failed schema validation. `ApiErrorIssue::pointer()` renders the path as `items.0.price` for mapping straight onto form fields. Malformed entries are skipped rather than failing the whole envelope.

- **`VcrApiException::$requestId`** — correlation id on unexpected 5xx responses. Quote it to support to locate the matching log entry.

## [0.6.0] — 2026-07-10

### Added

- **`RegisterSaleInput` now accepts an optional `comment`** — a merchant-internal
  note on the sale, e.g. an external payment reference for reconciliation. Pass
  it via the constructor or either factory
  (`RegisterSaleInput::withAmount(..., comment: 'Stripe pi_3Qc...')`,
  `withAutoSettle(..., comment: ...)`). Max 500 characters; the server strips
  control characters and trims whitespace. The `comment` key is appended to the
  wire payload only when non-null, so existing callers are byte-compatible. It
  is purely internal — never printed on the buyer's receipt nor sent to the tax
  authority.
- **`SaleDetail::$comment`** — `getSale()` now exposes the sale's comment (or
  `null` when none was set).

## [0.5.0] — 2026-07-09

Brings the SDK level with `/api/v1`: foreign-currency sales, derived-total
payment, the offer catalogue read/rename endpoints, and a rate preview.

### Added

- **`VcrClient::listOffers(?string $externalId, ?OfferType $type, bool $includeArchived)`**,
  **`getOffer(int $offerId)`**, and **`updateOffer(int $offerId, OfferTitle $title)`** —
  wrap `GET /offers`, `GET /offers/{id}`, and `PATCH /offers/{id}`. Read the
  catalogue (up to 500 rows, filterable), fetch a single offer, and rename an
  offer's title going forward — already-issued receipts and the SRC fiscal
  record are unchanged. New models `Model\OfferListItem`,
  `Model\OfferDefaultDepartment`.
- **`SaleItem::$currency`** — optional per-item ISO 4217 input currency for
  foreign-currency sales (HO-234-N). When set to a non-AMD code, `price` is
  denominated in that currency and the VCR converts every line to AMD
  server-side at the previous-business-day CBA rate; the fiscal receipt is
  always AMD. All foreign-priced items in one sale must share the same
  currency. Omit (or pass `"AMD"`) for a native AMD line.
- **`RegisterSaleInput` auto-settle** — a sale now settles by *exactly one* of
  explicit `amount` (`Input\SaleAmount`) or the new `autoSettle`
  (`Input\AutoSettle` + `AutoSettleTender` enum), where the VCR derives the
  whole AMD cart total and charges it to one tender. Required for
  foreign-currency sales, where the AMD total isn't knowable client-side. Use
  the `RegisterSaleInput::withAmount()` / `withAutoSettle()` named constructors
  for a clear call site.
- **`VcrClient::getExchangeRate(string $currency)`** — wraps `GET /exchange-rate`.
  Previews the AMD conversion rate the VCR would apply now (CBA mid-market rate,
  previous business day, HO-234-N). Read-only; rejects `AMD` server-side. New
  model `Model\ExchangeRate`.

- **`VcrClient::listDepartments()`** — wraps `GET /departments`. Returns
  `list<DepartmentListItem>` with `internalId`, `externalId`, `taxRegime`,
  and the localised `title` keyed by language. Only departments confirmed
  by the tax service are returned, so each `internalId` is safe to reference
  from a sale item's `department.id`. Closes the gap where the SDK could
  create departments but not list them.
- New models: `Model\DepartmentListItem`, `Model\DepartmentLocalizedTitle`.

- **`VcrClient::listPrepayments(?string $customerRef, ?PrepaymentState $state)`** —
  wraps `GET /prepayments`. Returns a `list<PrepaymentListItem>` capped at 500;
  each item carries the ledger-derived `remaining` and `state`.
- **`VcrClient::getCustomerPrepaymentBalance(string $customerRef)`** — wraps
  `GET /prepayments/balance`. Returns a `CustomerPrepaymentBalance` with the
  total open balance for that customer (scoped to the BusinessEntity that
  owns the calling VCR's API key) plus the FIFO-ordered list of contributing
  open prepayments.
- New types: `BlobSolutions\VcrAm\PrepaymentState` enum (`Open` /
  `Consumed` / `Refunded`), `Model\PrepaymentListItem`,
  `Model\CustomerPrepaymentBalance`, `Model\CustomerOpenPrepayment`.

### Changed

- **`RegisterSaleInput::__construct`** — `$amount` is now nullable
  (`?SaleAmount`) so it can be omitted in favour of `$autoSettle`; a trailing
  `?AutoSettle $autoSettle = null` parameter was added. Exactly one of the two
  must be present (enforced at construction), mirroring the server. Existing
  `new RegisterSaleInput($cashier, $items, $amount, $buyer)` call sites are
  unaffected.

- **`Model\PrepaymentDetail`** now also exposes `remaining: float` and
  `state: PrepaymentState`, mirroring the server response. Strictly additive
  — existing field access is unchanged.

- **`VcrClient::whoami()`** — new endpoint that returns the VCR identity the
  calling API key belongs to: VCR id, CRN, mode (`production` / `sandbox`),
  trading platform name, and the owning business entity's TIN and English
  name. Useful for SDK health checks and for distinguishing
  production-vs-sandbox keys in client-side diagnostics. The companion
  `BlobSolutions\VcrAm\VcrMode` enum and
  `BlobSolutions\VcrAm\Model\AccountInfo` /
  `BlobSolutions\VcrAm\Model\AccountBusinessEntity` value objects ship in
  this release.
- Works pre-activation: a freshly-imported VCR that does not yet have a
  CRN returns `crn: null` rather than 403.

## [0.4.0] — 2026-05-13

### Breaking

- **`CreateDepartmentInput::__construct`** now requires `LocalizedName $title`
  as the second positional argument (before the optional `$externalId`).
  The server-side endpoint previously persisted departments without a
  title, which crashed X/Z reports for any sale that referenced one. The
  0.3.0 call shape (`new CreateDepartmentInput(taxRegime: ..., externalId: ...)`)
  is no longer accepted by the server. Update calls to:

  ```php
  new CreateDepartmentInput(
      taxRegime: TaxRegime::Vat,
      title: new LocalizedName(
          value: ['hy' => 'Մթերք', 'ru' => 'Продукты', 'en' => 'Groceries'],
          localizationStrategy: LocalizationStrategy::Translation,
      ),
      externalId: 'erp-dept-bakery',
  );
  ```

## [0.3.0] — 2026-05-04

### Added

- **`SaleItem.emarks`** — optional list of excise-mark identifiers consumed
  by the line item (alcohol, tobacco, pharmaceuticals — Govt Decision
  1976-N, effective 2026-05-01). Mirrors the field already present on
  `RefundItemInput` and the upstream TS schema. Per-item by domain
  design: the wire format flattens marks into a single top-level array
  per receipt at the VCR API boundary, but the SDK preserves item-level
  grouping so refund flows can subset codes against the original sale.
  Constructor enforces non-empty entries; full format bounds (charset,
  29-128 chars) live at the VCR API boundary.

### Compatibility

- **Backwards-compatible.** New constructor parameter is the last
  positional argument and defaults to `null`; existing call sites
  continue to compile and serialize identically.
- Receipts containing no marked goods serialize without an `emarks`
  field on each item, matching pre-0.3.0 wire output byte-for-byte.

## [0.2.0] — 2026-05-04

### Changed

- **No functional API changes.** This release synchronises with the first
  release of the sibling [`blob-solutions/laravel-vcr-am`](https://packagist.org/packages/blob-solutions/laravel-vcr-am)
  Laravel adapter under the project's sync-versioning policy: every tag
  bumps every published package to the same version, even ones whose
  source did not change. See [`docs/releasing.md`](https://github.com/blob-am/vcr-am-php/blob/main/docs/releasing.md#versioning).

### Internal

- Source repository restructured into a monorepo at [`blob-am/vcr-am-php`](https://github.com/blob-am/vcr-am-php).
  The Composer package is now mirrored from `packages/sdk/` to the
  read-only repository [`blob-am/vcr-am-sdk-php`](https://github.com/blob-am/vcr-am-sdk-php)
  on every release tag via `splitsh`. End users see no difference —
  `composer require blob-solutions/vcr-am-sdk` resolves and installs
  identically to `0.1.x`.

## [0.1.1] — 2026-05-02

### Fixed

- **Auth header**: SDK now sends the API key as `X-API-Key`, matching the
  VCR.AM API contract. v0.1.0 sent `Authorization: Bearer <key>`, which
  the server rejects with HTTP 400 "X-API-Key header is required".

### Notes

- **v0.1.0 is broken** against production. Upgrade to 0.1.1 immediately.
  No usage of v0.1.0 will succeed; no migration is required besides the
  version bump.

## [0.1.0] — 2026-05-02

> ⚠️ **Yanked.** Authentication header was wrong (`Authorization: Bearer …`
> instead of `X-API-Key: …`). All requests against the production API
> return HTTP 400. Use 0.1.1 or later.

### Added

- Initial public release covering all 11 VCR.AM API endpoints at full
  TS-SDK parity: `listCashiers`, `createCashier`, `createDepartment`,
  `createOffer`, `searchClassifier`, `registerSale`, `getSale`,
  `registerSaleRefund`, `registerPrepayment`, `getPrepayment`,
  `registerPrepaymentRefund`.
- Three-tier exception hierarchy: `VcrApiException` (non-2xx),
  `VcrNetworkException` (transport failure), `VcrValidationException`
  (response schema mismatch).
- Constructor-validated input DTOs: TIN format, decimal-string regex,
  cashier PIN format, mandatory Armenian localisation.
- API-key header redaction on every exception's request copy, cashier
  PIN redaction via `__debugInfo()`.
- PSR-3 logger / PSR-17 factories / PSR-18 client all overridable;
  defaults discovered via `php-http/discovery`.
- 100% line + type coverage gated in CI on PHP 8.5; matrix tested on
  PHP 8.2 / 8.3 / 8.4 / 8.5.
