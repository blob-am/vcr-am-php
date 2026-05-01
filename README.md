# VCR.AM PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/blob-solutions/vcr-am-sdk.svg)](https://packagist.org/packages/blob-solutions/vcr-am-sdk)
[![PHP Version Require](https://img.shields.io/packagist/dependency-v/blob-solutions/vcr-am-sdk/php)](https://packagist.org/packages/blob-solutions/vcr-am-sdk)
[![License](https://img.shields.io/packagist/l/blob-solutions/vcr-am-sdk.svg)](LICENSE)
[![CI](https://github.com/blob-am/vcr-am-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/blob-am/vcr-am-sdk-php/actions/workflows/ci.yml)

Official PHP SDK for the [VCR.AM](https://vcr.am) Virtual Cash Register API. Fiscalize sales, refunds, and prepayments through Armenia's State Revenue Committee — without touching XML, PSR-7, or wire-format quirks.

A native sibling to the [TypeScript SDK](https://github.com/blob-am/vcr-am-sdk). Same endpoints, same error semantics, same response validation philosophy — adapted to idiomatic PHP 8.2+.

## Status

> **Pre-release.** API is being developed in lockstep with the TypeScript SDK. While the package is on `0.x`, every minor release may introduce breaking changes — pin tightly until `1.0`.

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

```php
use BlobSolutions\VcrAm\VcrClient;

$client = new VcrClient(apiKey: $_ENV['VCR_AM_API_KEY']);
```

API surface comes online endpoint-by-endpoint as the implementation lands. Track progress in [CHANGELOG.md](CHANGELOG.md).

## Configuration

```php
use BlobSolutions\VcrAm\VcrClient;

$client = new VcrClient(
    apiKey: $apiKey,
    baseUrl: VcrClient::DEFAULT_BASE_URL,  // 'https://app.vcr.am'
    timeoutMs: VcrClient::DEFAULT_TIMEOUT_MS,
    httpClient: $myPsr18Client,            // optional override
    requestFactory: $myPsr17Factory,       // optional override
    streamFactory: $myPsr17Factory,        // optional override
    logger: $myPsr3Logger,                 // optional override (defaults to NullLogger)
);
```

All optional dependencies are PSR-standardised — bring your own Guzzle/Symfony/Slim stack, or accept the bundled defaults.

## Idempotency and retries

The SDK does **not** retry failed requests automatically. Fiscalization endpoints are not guaranteed to be idempotent on the server side; a silent retry could double-register a sale. If you need retries, wrap the client in your own logic with explicit idempotency tracking.

## Compatibility

| SDK version | PHP versions tested |
|---|---|
| `^0.x` | 8.2, 8.3, 8.4 |

PHP 8.5 will be added once `cuyz/valinor` ships a release that declares 8.5 support — the SDK itself works on 8.5, but the validation library currently caps at 8.4.

## Development

```bash
composer install
composer check    # format check + phpstan + tests
composer format   # apply Pint fixes
```

## License

ISC © Alex Kraiz, Blob Solutions
