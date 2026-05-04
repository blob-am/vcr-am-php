# VCR.AM PHP Monorepo

This repository hosts the PHP-side of the [VCR.AM](https://vcr.am) Virtual Cash Register ecosystem: a framework-agnostic SDK plus thin adapters for popular PHP frameworks and e-commerce platforms.

## Packages

| Package | Composer name | Status |
| --- | --- | --- |
| [`packages/sdk`](packages/sdk) | [`blob-solutions/vcr-am-sdk`](https://packagist.org/packages/blob-solutions/vcr-am-sdk) | Released (`0.x`) — framework-agnostic SDK. PSR-3, PSR-17, PSR-18. |
| [`packages/laravel`](packages/laravel) | `blob-solutions/laravel-vcr-am` | Pre-release — thin Laravel adapter (ServiceProvider, Facade, config, Artisan). Tagged & published from Phase 4 onwards. |

Each package is published to Packagist independently from a read-only mirror repository (split out of this monorepo on every tag). End users `composer require` packages directly — they never need to clone this monorepo.

## Why a monorepo

- **Atomic changes** — when the SDK signature evolves, every adapter PR lands in the same commit and CI runs as one matrix.
- **Coordinated versions** — adapters stay in lockstep with SDK majors; no compatibility matrix to maintain.
- **One source of truth** — single Pint style, single PHPStan baseline, single CI configuration.

The pattern follows Symfony's and Laravel's monorepo layouts: source lives here, individual packages are mirrored to their own repos via `splitsh/lite` on tag push.

## Repository layout

```
.
├── bin/each                ← run `composer <cmd>` in every package
├── composer.json           ← orchestrator (not published)
├── packages/
│   ├── sdk/                → mirror: blob-am/vcr-am-sdk          → blob-solutions/vcr-am-sdk
│   └── laravel/            → mirror: blob-am/laravel-vcr-am      → blob-solutions/laravel-vcr-am
└── .github/workflows/
    ├── ci.yml              ← matrix tests for every package
    └── release.yml         ← splitsh on `v*` tag, GitHub release
```

## Local development

Requirements: PHP 8.2+, Composer 2.x, Bash.

```bash
# Install dependencies for every package
composer install:all

# Run the full local CI gate (pint + phpstan + pest) across every package
composer check

# Per-package commands work too
cd packages/sdk && composer test
```

Each package has its own `composer.json` and is fully self-contained — you can develop and test it standalone after `cd packages/<name>`.

## Versioning

All packages share a single version tag. A `v0.5.2` tag in this repo produces a `0.5.2` release of every published package, even ones whose source did not change in that release. This keeps cross-package compatibility trivial: `laravel-vcr-am ^0.5` always pairs with `vcr-am-sdk ^0.5`.

## Releasing

1. Tag the monorepo: `git tag v0.X.Y && git push origin v0.X.Y`
2. The `release.yml` workflow runs `splitsh/lite` per package, pushes the subtree to its mirror repo, and re-tags the mirror.
3. Packagist webhooks pick up the new tag automatically.

See [`docs/releasing.md`](docs/releasing.md) for the full procedure (added with Phase 3).

## License

[ISC](LICENSE) — same for every package in the monorepo.
