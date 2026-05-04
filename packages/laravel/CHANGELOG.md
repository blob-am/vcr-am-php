# Changelog

All notable changes to `blob-solutions/laravel-vcr-am` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] — 2026-05-04

First release. Starts at `0.2.0` (not `0.1.0`) to align with the
synchronised version of the sibling [`blob-solutions/vcr-am-sdk`](https://packagist.org/packages/blob-solutions/vcr-am-sdk)
package — the project tags both packages together. See [`docs/releasing.md`](https://github.com/blob-am/vcr-am-php/blob/main/docs/releasing.md#versioning)
for the sync-versioning policy.

### Added

- Thin Laravel adapter on top of [`blob-solutions/vcr-am-sdk`](https://packagist.org/packages/blob-solutions/vcr-am-sdk).
  Zero business logic — every API call goes straight through to the SDK.
- `VcrAmServiceProvider` — registers `VcrClient` as a singleton, reads
  credentials from `config/vcr-am.php`, and forwards Laravel's PSR-3
  logger plus any container-bound PSR-17/18 implementations into the
  SDK constructor.
- `VcrAm` facade — `VcrAm::registerSale(...)` style access to every SDK
  method, with a complete `@method` block for IDE autocompletion.
- Auto-discovered service provider and facade alias via Laravel's
  package discovery (`extra.laravel`).
- Publishable config: `php artisan vendor:publish --tag=vcr-am-config`.
  Defaults read `VCR_AM_API_KEY` and `VCR_AM_BASE_URL` from `.env`;
  empty / whitespace `base_url` is treated as null and falls through to
  the SDK default rather than producing requests against an empty host.
- `vcr-am:health` Artisan command — verifies API connectivity by
  calling `listCashiers()`. Exits 0 on success, 1 with the SDK
  exception message on failure.

### Requirements

- PHP **8.2 or newer**
- Laravel **11.x, 12.x, or 13.x** (`illuminate/contracts ^11.0 || ^12.0 || ^13.0`)
- Built on `spatie/laravel-package-tools ^1.16` (de-facto standard for
  modern Laravel package authoring)
