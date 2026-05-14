# Changelog

All notable changes to `blob-solutions/laravel-vcr-am` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- **`VcrAm::sandbox()` + `VCR_AM_SANDBOX_API_KEY`** — a parallel `VcrClient`
  is registered under the `vcr-am.sandbox` container binding when
  `VCR_AM_SANDBOX_API_KEY` is set. Useful when a single app needs both a
  production and a sandbox client wired side-by-side (E2E suites, demos,
  staging smoke tests). Calling `VcrAm::sandbox()` without the env var
  throws a clear `RuntimeException` so production code never silently
  downgrades to the default key.
- **`VcrAm::fake()`** — drop-in test double modelled on Laravel's
  `Http::fake()`. Rebinds both the production and sandbox `VcrClient`
  bindings onto a PSR-18 fake that records every request and rejects
  un-stubbed endpoints with a clear `RuntimeException`. Ships with
  `assertSent()`, `assertNotSent()`, `assertNothingSent()`, and
  `assertSentCount()` assertion helpers. Stub matchers read like the
  SDK source — `"POST /sales"`, `"GET /cashiers"` — and accept either an
  array (autowrapped into a 200 JSON response) or a closure that returns
  a PSR-7 `ResponseInterface`.
- **`vcr-am:health`** now prints the VCR's name, CRN, operating mode
  (production / sandbox), and the owning entity. Add `--sandbox` to run
  the check against the sandbox client.
- README section: [Testing](README.md#testing).

### Changed

- Requires `blob-solutions/vcr-am-sdk` with the new `whoami()` endpoint
  (currently `dev-main`, will resolve to the next tagged SDK release).

## [0.4.0] — 2026-05-13

### Breaking (propagated from SDK)

- **No functional adapter changes.** Synchronises with [`blob-solutions/vcr-am-sdk@0.4.0`](https://packagist.org/packages/blob-solutions/vcr-am-sdk), which makes `CreateDepartmentInput::__construct` require a `LocalizedName $title` argument. Laravel call sites that build the input via `new CreateDepartmentInput(...)` must add the `title:` parameter — see the [SDK CHANGELOG](https://github.com/blob-am/vcr-am-sdk-php/blob/main/CHANGELOG.md#040--2026-05-13).

## [0.3.0] — 2026-05-04

### Changed

- **No functional adapter changes.** This release synchronises with
  [`blob-solutions/vcr-am-sdk@0.3.0`](https://packagist.org/packages/blob-solutions/vcr-am-sdk),
  which added the `SaleItem.emarks` field for excise-marked goods
  (alcohol, tobacco, pharmaceuticals — Govt Decision 1976-N). Laravel
  applications get the new field automatically through the SDK
  constructor; no provider, facade, or config changes required. See the
  [SDK CHANGELOG](https://github.com/blob-am/vcr-am-sdk-php/blob/main/CHANGELOG.md#030--2026-05-04)
  for details and the [project sync-versioning policy](https://github.com/blob-am/vcr-am-php/blob/main/docs/releasing.md#versioning).

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
