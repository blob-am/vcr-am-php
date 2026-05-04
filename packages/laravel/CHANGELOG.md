# Changelog

All notable changes to `blob-solutions/laravel-vcr-am` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial Laravel adapter for the [`blob-solutions/vcr-am-sdk`](https://packagist.org/packages/blob-solutions/vcr-am-sdk) PHP SDK.
- `VcrAmServiceProvider` — registers `VcrClient` as a singleton, reads credentials from `config/vcr-am.php`, and forwards Laravel's PSR-3 logger into the SDK.
- `VcrAm` facade — `VcrAm::registerSale(...)` style access to every SDK method.
- Auto-discovered service provider and facade alias via Laravel's package discovery.
- Publishable config (`php artisan vendor:publish --tag=vcr-am-config`).
- `vcr-am:health` Artisan command — verifies API connectivity by calling `listCashiers()`.
