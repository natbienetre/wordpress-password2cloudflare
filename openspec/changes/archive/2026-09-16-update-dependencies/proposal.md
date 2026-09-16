## Why

Dependencies are pinned to old major/minor versions and have not been kept current: `composer.json` allows Guzzle `^7.7` (latest `8.2.0`), PHPUnit `^9` (latest major `13.x`), `yoast/phpunit-polyfills` `^2` (latest `4.x`), and `composer/installers` `^2.2` (latest `2.3.0`); `wp-cli/wp-cli-bundle` is unpinned (`*`) but locked to `2.8.1` versus latest `2.12.0`. GitHub Actions in `.github/workflows/*.yml` also reference stale major versions (`actions/checkout@v2`/`@v3`, `actions/cache@v3`) while `actions/upload-artifact@v4` is already current. `.github/dependabot.yml` only tracks the `composer` ecosystem, so Actions versions drift silently and require manual discovery like this one. Keeping dependencies current reduces exposure to unpatched vulnerabilities (GitHub currently reports 27 open Dependabot alerts on `main`), keeps CI runners on supported action versions, and avoids a larger, riskier jump later.

## What Changes

- Update Composer dependencies in `composer.json`/`composer.lock` to the latest versions compatible with this plugin's supported PHP/WordPress versions, evaluating major-version bumps case by case:
  - `guzzlehttp/guzzle` `^7.7` → latest (evaluate `^8` for breaking changes in HTTP client usage in `classes/`).
  - `composer/installers` `^2.2` → `^2.3`.
  - `wp-cli/wp-cli-bundle` → latest compatible dev tool version.
  - `phpunit/phpunit` `^9` → evaluate newer major (constrained by supported PHP version and `yoast/phpunit-polyfills` compatibility matrix).
  - `yoast/phpunit-polyfills` `^2` → version compatible with the selected PHPUnit major.
- Update pinned third-party GitHub Actions in `.github/workflows/release.yml` and `.github/workflows/wordpress-plugin.yml` to their latest stable major versions (e.g., `actions/checkout@v2`/`@v3` → `@v4`, `actions/cache@v3` → latest, `ncipollo/release-action@v1`, `ibiqlik/action-yamllint@v3`, `overtrue/phplint@9.1.2`, `holyhope/test-wordpress-plugin-github-action@v2.0.2`, `mikepenz/action-junit-report@v3`, `holyhope/test-wordpress-languages-github-action@v4.0.1`), confirming each new major doesn't change required inputs/outputs used in these workflows.
- Extend `.github/dependabot.yml` to also track the `github-actions` ecosystem (in addition to the existing `composer` entry) so future Action version drift is caught automatically.
- Run the existing test suite (`composer test` / CI) against the updated dependencies and fix any breakage surfaced by major-version bumps before merging.
- **BREAKING** (conditional): if `guzzlehttp/guzzle` or `phpunit/phpunit` are bumped to their latest major version, this may require source-level fixes in `classes/` (Guzzle client usage) or `tests/` (PHPUnit assertions/attributes) to stay compatible. These fixes are in scope for this change but will be scoped down to a minor bump if a major bump proves too disruptive.

## Capabilities

This is a dependency-maintenance and CI-tooling change: it updates the versions of libraries and Actions the plugin builds and tests with, without altering the plugin's observable password-sync behavior or introducing/removing any user-facing capability. No `specs/` deltas apply; `skip_specs: true` is set in `.openspec.yaml`.

### New Capabilities
None.

### Modified Capabilities
None.

## Impact

- **Affected files**: `composer.json`, `composer.lock`, `.github/workflows/release.yml`, `.github/workflows/wordpress-plugin.yml`, `.github/dependabot.yml`.
- **Possibly affected code**: any usage of the Guzzle HTTP client in `classes/` (if bumped to Guzzle 8), and PHPUnit test syntax in `tests/` (if bumped to a newer PHPUnit major which may drop PHPUnit 9-era APIs even with `yoast/phpunit-polyfills`).
- **CI**: `wordpress-plugin.yml` (lint, phplint, tests, i18n check) and `release.yml` (build + GitHub release) must both pass with the updated Action versions and dependency versions before this change is merged.
- **No runtime/user-facing impact**: end users of the plugin see no behavior change; this is a maintenance change to keep the build/test/release pipeline on supported, patched dependency versions.
