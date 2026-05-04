# Changelog

All notable changes to this package will be documented in this file.

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
