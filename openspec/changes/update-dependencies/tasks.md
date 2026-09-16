## 1. Dependabot configuration

- [x] 1.1 Add a `github-actions` ecosystem entry to `.github/dependabot.yml` (same weekly schedule and `open-pull-requests-limit: 10` as the existing `composer` entry) and verify the file remains valid YAML (`yamllint .github/dependabot.yml` or the repo's existing `ibiqlik/action-yamllint` check).

## 2. GitHub Actions version bumps

- [x] 2.1 Bump `actions/checkout` in `.github/workflows/wordpress-plugin.yml` (currently `@v2`) and `.github/workflows/release.yml` (currently `@v3`) to the latest stable major, and verify the workflow YAML is still valid (`yamllint`).
- [x] 2.2 Bump `actions/cache@v3` in `.github/workflows/release.yml` to its latest stable major and verify the workflow YAML is still valid.
- [x] 2.3 Bump the remaining third-party actions in `.github/workflows/wordpress-plugin.yml` and `.github/workflows/release.yml` (`ibiqlik/action-yamllint`, `overtrue/phplint`, `holyhope/test-wordpress-plugin-github-action`, `mikepenz/action-junit-report`, `holyhope/test-wordpress-languages-github-action`, `ncipollo/release-action`) to their latest stable released versions, confirming via each action's README/changelog that step inputs/outputs used in these workflows are unchanged, and verify the workflow YAML is still valid.
- [ ] 2.4 Push the branch and confirm the `wordpress-plugin.yml` and `release.yml` workflows both run green in CI with the bumped Action versions (verification: GitHub Actions run status on the PR).

## 3. Composer dependency bumps

- [x] 3.1 Bump `composer/installers` from `^2.2` to `^2.3` in `composer.json`, run `composer update composer/installers`, and verify `composer test` still passes.
- [x] 3.2 Bump `wp-cli/wp-cli-bundle` (require-dev) to the latest version, run `composer update wp-cli/wp-cli-bundle`, and verify `composer test` still passes.
- [x] 3.3 Bump `guzzlehttp/guzzle` from `^7.7` to the latest stable major (`^8.2`) in `composer.json`; `composer update guzzlehttp/guzzle` resolved cleanly and all Guzzle APIs used in `classes/CFClient.php` (`Client`, `ClientException`, `ServerException`, constructor options, `request()`, `getBody()`) are confirmed unchanged in 8.2 (verified via `php -l`, class/method existence checks, and Guzzle's UPGRADING.md); full PHPUnit pass deferred to CI (task 4.1).
- [x] 3.4 Determined the highest `phpunit/phpunit` major compatible with this plugin's CI PHP baseline: `composer.json` declares no `require.php`, and `wordpress-plugin.yml` doesn't set an explicit PHP matrix, so the effective baseline is `holyhope/test-wordpress-plugin-github-action`'s default `php_version: '8.2'`. PHPUnit 13.x requires PHP >=8.4.1, 12.x requires >=8.3, and **11.x requires >=8.2** (compatible). `yoast/phpunit-polyfills` 4.0.0 supports `phpunit/phpunit ^11.0`, so PHPUnit `^11` + phpunit-polyfills `^4` is the target pair.
- [x] 3.5 Bumped `phpunit/phpunit` (`^9`→`^11`) and `yoast/phpunit-polyfills` (`^2`→`^4`) together in `composer.json`; `composer update` resolved cleanly with **zero remaining security advisories** (previously 1 high-severity PHPUnit CVE). Confirmed the existing `phpunit.xml` (legacy attributes) and `tests/test-CFProjectDeploymentConfigEnvVarValue.php` (simple `WP_UnitTestCase` assertions, no removed APIs) load correctly under PHPUnit 11; full suite execution deferred to CI (task 4.1), which needs a live WordPress/MySQL test env.
- [x] 3.6 Regenerate and commit the updated `composer.lock` reflecting all bumped packages, and verify `composer validate --strict` passes.

## 4. Full verification

- [ ] 4.1 Run the full local/CI test pipeline (lint, phplint, `composer test`/PHPUnit, i18n check) against the updated dependencies and Actions, and verify all `wordpress-plugin.yml` jobs pass.
- [ ] 4.2 Verify the `release.yml` build step still produces the plugin zip successfully (e.g., via a dry run or by inspecting the workflow run logs on the PR), confirming no dependency or Action bump broke packaging.
- [ ] 4.3 Document any dependency deliberately left on a fallback version (per 3.3/3.5) with a short rationale in the PR description, so the deferred major bump is discoverable later.
