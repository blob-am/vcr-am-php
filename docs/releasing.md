# Releasing

This monorepo publishes two Composer packages — `blob-solutions/vcr-am-sdk` and `blob-solutions/laravel-vcr-am` — by mirroring `packages/sdk/` and `packages/laravel/` to two read-only repositories on tag push. The mirrors are what Packagist consumes; end users never clone this monorepo.

## TL;DR

```bash
git tag v0.X.Y
git push origin v0.X.Y
```

Both packages get a synchronous v0.X.Y release. Packagist auto-updates within seconds.

## How the pipeline works

Tag push (`v*`) on `main` triggers [`.github/workflows/release.yml`](../.github/workflows/release.yml):

1. **`github-release`** — creates a release on the monorepo with auto-generated notes from PR titles.
2. **`split-sdk`** — mirrors `packages/sdk/` → [`blob-am/vcr-am-sdk`](https://github.com/blob-am/vcr-am-sdk) using [`danharrin/monorepo-split-github-action`](https://github.com/danharrin/monorepo-split-github-action). Pushes both the subtree commits and the same tag.
3. **`split-laravel`** — mirrors `packages/laravel/` → [`blob-am/laravel-vcr-am`](https://github.com/blob-am/laravel-vcr-am). Same as `split-sdk` but with a **sanitisation step** first.

### Why Laravel needs sanitisation

`packages/laravel/composer.json` carries dev-only knobs so monorepo contributors' local Laravel tests resolve against the in-tree SDK source:

```json
"repositories": [
    { "type": "path", "url": "../sdk", "options": { "symlink": true } }
],
"minimum-stability": "dev",
"prefer-stable": true,
"require": {
    "blob-solutions/vcr-am-sdk": "^0.1 || dev-main"
}
```

End users installing the mirror only see Packagist. A path repository whose target does not exist makes `composer install` **hard error**:

> `The url supplied for the path (../sdk) repository does not exist`

The release workflow:
1. Strips `repositories`, `minimum-stability`, `prefer-stable`.
2. Replaces the `vcr-am-sdk` constraint with `^${MAJOR_MINOR}.0` of the released tag (sync versioning — Laravel `0.X.Y` requires SDK `^0.X.0`).
3. Amends the tag commit so splitsh picks up the sanitised state.
4. Runs splitsh.

The amend is local to the GitHub Actions runner — it never reaches the monorepo's `main` branch. The mirror's git log shows the sanitised commit; the monorepo's git log keeps the dev-friendly version.

## One-time setup

Before the **first** tag push, complete these three steps. Skipping any of them makes the release workflow fail.

### 1. Create the two mirror repositories on GitHub

Both must exist as **empty** GitHub repos under the `blob-am` organisation:

- `blob-am/vcr-am-sdk` — public, ISC licence, no README/`.gitignore` (splitsh will populate from monorepo content)
- `blob-am/laravel-vcr-am` — public, ISC licence, no initial files

```bash
# With the gh CLI:
gh repo create blob-am/vcr-am-sdk --public --description "Official PHP SDK for the VCR.AM Virtual Cash Register API"
gh repo create blob-am/laravel-vcr-am --public --description "Official Laravel adapter for blob-solutions/vcr-am-sdk"
```

### 2. Generate a Personal Access Token with mirror push rights

The default `GITHUB_TOKEN` only has access to the workflow's own repository, so it cannot push to `blob-am/vcr-am-sdk` or `blob-am/laravel-vcr-am`.

Create a fine-grained PAT at <https://github.com/settings/tokens?type=beta> with:

- **Repository access:** `blob-am/vcr-am-sdk` and `blob-am/laravel-vcr-am` (only these two)
- **Permissions → Repository:** `Contents: Read and write`, `Metadata: Read-only`
- **Expiry:** 1 year (set a calendar reminder to rotate)

Classic PATs work too (`repo` scope) but fine-grained is preferable.

### 3. Add the PAT as a monorepo secret

In `blob-am/vcr-am-sdk-php` → Settings → Secrets and variables → Actions → New repository secret:

- **Name:** `MIRROR_PUSH_TOKEN`
- **Value:** the PAT generated above

### 4. Register both mirrors on Packagist

After the **first** successful release pushes content to the mirrors:

- <https://packagist.org/packages/submit> — paste `https://github.com/blob-am/vcr-am-sdk` (re-point if `blob-solutions/vcr-am-sdk` already exists from the pre-monorepo era)
- <https://packagist.org/packages/submit> — paste `https://github.com/blob-am/laravel-vcr-am`

Enable the GitHub webhook on each mirror (Packagist provides the URL on first submission) so subsequent tags publish automatically without manual `Update` clicks.

## Versioning

**Synchronous.** Every tag bumps every published package to the same version, even ones whose source did not change in that release. Rationale:

- Mental model is trivial — `laravel-vcr-am ^0.5` always pairs with `vcr-am-sdk ^0.5`.
- No compatibility matrix to maintain.
- Removes a class of inter-package version-mismatch bugs.

Cost: a "Laravel-only" fix still bumps the SDK to a new patch version with zero source changes. That's a no-op publish on Packagist — tolerable.

The Laravel adapter's `composer.json` is rewritten at release time to require `blob-solutions/vcr-am-sdk: ^MAJOR.MINOR.0` (e.g. tag `v0.3.7` → constraint `^0.3.0`). Patch-level mismatches between the two packages are allowed; minor and major must match.

## Cutting a release

1. Make sure `main` is green on CI.
2. Update both `CHANGELOG.md` files (`packages/sdk/CHANGELOG.md`, `packages/laravel/CHANGELOG.md`) — move "Unreleased" entries under a new `## [v0.X.Y] - YYYY-MM-DD` header.
3. Commit:
   ```bash
   git add packages/*/CHANGELOG.md
   git commit -m "chore(release): v0.X.Y"
   git push origin main
   ```
4. Wait for CI green.
5. Tag and push:
   ```bash
   git tag v0.X.Y
   git push origin v0.X.Y
   ```
6. Watch the run in <https://github.com/blob-am/vcr-am-sdk-php/actions>.
7. Verify:
   - `https://packagist.org/packages/blob-solutions/vcr-am-sdk` shows the new version
   - `https://packagist.org/packages/blob-solutions/laravel-vcr-am` shows the new version
   - `https://github.com/blob-am/vcr-am-sdk/tags` has `v0.X.Y`
   - `https://github.com/blob-am/laravel-vcr-am/tags` has `v0.X.Y`

## Yanking a bad release

Composer doesn't have a true "yank" — you can only mark a tag as bad on Packagist. Steps:

1. Cut a new patch release with the fix as `v0.X.(Y+1)`.
2. On Packagist, both packages → Maintenance tab → mark `v0.X.Y` as `abandoned: blob-solutions/...:^0.X.(Y+1)` so Composer warns when resolving.
3. Optionally delete the bad tag from the mirror repos (keeping it in the monorepo for git history):
   ```bash
   gh api -X DELETE repos/blob-am/vcr-am-sdk/git/refs/tags/v0.X.Y
   gh api -X DELETE repos/blob-am/laravel-vcr-am/git/refs/tags/v0.X.Y
   ```
   Packagist refreshes within minutes.

## Troubleshooting

**`The url supplied for the path (../sdk) repository does not exist`** when an end user runs `composer require blob-solutions/laravel-vcr-am`. The sanitisation step did not run (or the released composer.json was wrong). Cut a patch release after verifying the workflow's "Sanitize Laravel composer.json" step succeeded.

**`split-sdk` or `split-laravel` job fails with 403/404 on push.** The `MIRROR_PUSH_TOKEN` secret is missing, expired, or lacks write access to the target repo. Re-issue per setup step 2.

**`split-*` succeeds but Packagist still shows the old version.** The mirror's GitHub→Packagist webhook isn't wired. Add it on Packagist (the package page → Settings → GitHub Service Hook) or click `Update` manually once.

**Tag points to the wrong commit on the Laravel mirror after release.** Expected — the sanitised commit replaces the original tag commit on the mirror only. The monorepo's tag still points at the original. To inspect, `git log v0.X.Y` in each repo separately.
