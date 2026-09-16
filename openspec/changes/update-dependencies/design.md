## Context

See proposal.md - Why/What Changes for motivation and the full list of outdated dependencies and Actions. Key constraints:
- The plugin supports a range of PHP/WordPress versions (checked via CI in `.github/workflows/wordpress-plugin.yml`); any dependency bump must stay within whatever minimum PHP version this plugin currently declares as supported.
- `phpunit/phpunit` and `yoast/phpunit-polyfills` must remain mutually compatible (polyfills' major version tracks specific PHPUnit major ranges).
- CI (`wordpress-plugin.yml`) and release (`release.yml`) workflows must keep passing; they are the acceptance gate for this change, not new tests.
- `.github/dependabot.yml` currently only configures the `composer` ecosystem; GitHub Actions have no automated update path today.

## Goals / Non-Goals

**Goals:**
- Bring Composer dependencies and pinned GitHub Actions to current stable versions, minimizing security exposure and CI staleness.
- Add `github-actions` to `.github/dependabot.yml` so future Action drift is caught automatically, matching the existing `composer` entry.
- Keep all existing CI jobs (lint, phplint, tests, i18n check, release build) green after the bump.

**Non-Goals:**
- Rewriting or refactoring plugin code beyond the minimum changes needed to compile/run against updated dependency majors.
- Changing CI job structure, triggers, or adding new workflows.
- Upgrading the plugin's minimum supported PHP/WordPress version as a side effect (if a dependency major requires a newer PHP than currently supported, that dependency stays on its latest version compatible with the current minimum instead).

## Decisions

- **Composer dependencies: bump each package independently, preferring the latest major, but falling back to the latest minor within the current major if the new major breaks the test suite or requires a PHP bump.**
  Rationale: some majors here are meaningful jumps (Guzzle 7→8, PHPUnit 9→13) that can carry breaking API/runtime changes; blindly forcing the newest major risks unrelated breakage in a "just update dependencies" change. Alternative considered: force every package to latest major regardless of test outcome — rejected because it conflates a maintenance update with a potentially larger migration that deserves its own change.
- **`phpunit/phpunit` and `yoast/phpunit-polyfills` are bumped together, choosing the highest PHPUnit major that (a) supports the plugin's minimum PHP version and (b) has a compatible `yoast/phpunit-polyfills` release.**
  Rationale: these two packages are versioned as a pair; bumping one without the other breaks `require-dev`. Alternative considered: leave PHPUnit on `^9` and only bump polyfills — rejected, defeats the purpose of "update all the dependencies."
- **GitHub Actions: bump each action to its latest published major tag (e.g., `@v4`), keeping the same action (no replacing `actions/cache` or `ncipollo/release-action` with alternatives).**
  Rationale: this change is about staying current, not about re-architecting CI. Each workflow step's inputs/outputs are verified against the new major's changelog/README before merging.
- **Add `github-actions` ecosystem to `.github/dependabot.yml` in the same change**, using the same weekly schedule and PR limit as the existing `composer` entry, rather than a separate follow-up change.
  Rationale: the proposal's stated motivation ("Actions versions drift silently") is only fully addressed once Dependabot also watches Actions; doing it as a drive-by in this change is low-risk (pure config addition) and keeps the fix and its cause together.
- **Verification is CI-driven, not new hand-written tests.** The existing `composer test` / CI pipeline is the acceptance signal; no new test cases are added solely for this change.
  Rationale: this is a dependency-currency change, not a feature; the existing suite already exercises the plugin's behavior. Alternative considered: add regression tests per bumped dependency — rejected as out of proportion to a maintenance change, unless a bump surfaces a real gap.

## Risks / Trade-offs

- [Guzzle 7→8 changes HTTP client behavior/signatures used in `classes/`] → Attempt the bump; if it requires more than mechanical fixes (e.g., PSR-7/PSR-18 signature changes ripple through call sites), pin Guzzle to the latest `^7` instead and note the deferred major bump in tasks.md follow-ups.
- [PHPUnit 9→13 drops APIs/annotations still used in `tests/`, even with polyfills] → Attempt the bump to the highest PHPUnit major compatible with the plugin's minimum PHP version; if incompatible, fall back to the latest PHPUnit `^9.x` patch/minor and the matching polyfills version, and record the constraint (e.g., "blocked on PHP X support") in tasks.md.
- [A bumped GitHub Action changes required inputs/outputs or runner behavior, breaking CI silently] → Bump one workflow file at a time, push to a branch, and confirm the Actions run green in the PR checks before merging (this repo's normal PR CI gate) rather than merging on faith.
- [Enabling `github-actions` in Dependabot immediately opens several new PRs] → Accepted trade-off: this is the intended effect of closing the gap described in the proposal; `open-pull-requests-limit: 10` already caps volume, matching the existing `composer` entry.

## Migration Plan

1. Update `.github/dependabot.yml` to add the `github-actions` ecosystem entry (config-only, no risk).
2. Update `.github/workflows/release.yml` and `.github/workflows/wordpress-plugin.yml` Action version pins, one workflow at a time, verifying each via a CI run.
3. Update `composer.json` version constraints package by package (`composer/installers`, `wp-cli/wp-cli-bundle`, `guzzlehttp/guzzle`, `phpunit/phpunit` + `yoast/phpunit-polyfills` together), running `composer update <package>` and `composer test` after each to isolate any breakage to the package that caused it.
4. Regenerate `composer.lock` accordingly and commit it alongside `composer.json`.
5. Run the full CI pipeline (lint, phplint, tests, i18n check) on the branch/PR before merge.
6. Rollback strategy: since each dependency/Action is bumped and verified independently, a failure isolates to one package/workflow; revert that specific `composer.json`/workflow diff (or drop that one Action bump) rather than reverting the whole change.

## Open Questions

- Exact latest compatible PHPUnit major (and corresponding `yoast/phpunit-polyfills` version) depends on the plugin's currently-declared minimum supported PHP version, which will be checked from `composer.json`'s `require.php` (if present) or the WordPress-plugin-tests workflow matrix during implementation; this does not change the approach, only the specific version numbers chosen.
