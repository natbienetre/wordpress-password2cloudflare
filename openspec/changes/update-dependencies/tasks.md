## 1. Dependabot configuration

- [ ] 1.1 Add a `github-actions` ecosystem entry to `.github/dependabot.yml` (same weekly schedule and `open-pull-requests-limit: 10` as the existing `composer` entry) and verify the file remains valid YAML (`yamllint .github/dependabot.yml` or the repo's existing `ibiqlik/action-yamllint` check).

## 2. GitHub Actions version bumps

- [ ] 2.1 Bump `actions/checkout` in `.github/workflows/wordpress-plugin.yml` (currently `@v2`) and `.github/workflows/release.yml` (currently `@v3`) to the latest stable major, and verify the workflow YAML is still valid (`yamllint`).
- [ ] 2.2 Bump `actions/cache@v3` in `.github/workflows/release.yml` to its latest stable major and verify the workflow YAML is still valid.
- [ ] 2.3 Bump the remaining third-party actions in `.github/workflows/wordpress-plugin.yml` and `.github/workflows/release.yml` (`ibiqlik/action-yamllint`, `overtrue/phplint`, `holyhope/test-wordpress-plugin-github-action`, `mikepenz/action-junit-report`, `holyhope/test-wordpress-languages-github-action`, `ncipollo/release-action`) to their latest stable released versions, confirming via each action's README/changelog that step inputs/outputs used in these workflows are unchanged, and verify the workflow YAML is still valid.
- [ ] 2.4 Push the branch and confirm the `wordpress-plugin.yml` and `release.yml` workflows both run green in CI with the bumped Action versions (verification: GitHub Actions run status on the PR).

## 3. Composer dependency bumps

- [ ] 3.1 Bump `composer/installers` from `^2.2` to `^2.3` in `composer.json`, run `composer update composer/installers`, and verify `composer test` still passes.
- [ ] 3.2 Bump `wp-cli/wp-cli-bundle` (require-dev) to the latest version, run `composer update wp-cli/wp-cli-bundle`, and verify `composer test` still passes.
- [ ] 3.3 Attempt bumping `guzzlehttp/guzzle` from `^7.7` to the latest major (`^8`) in `composer.json`, run `composer update guzzlehttp/guzzle`, and verify `composer test` passes and any Guzzle client usage in `classes/` still works; if the major bump requires more than mechanical fixes, fall back to the latest `^7.x` version instead and note this in the change's follow-up notes.
- [ ] 3.4 Determine the highest `phpunit/phpunit` major compatible with this plugin's minimum supported PHP version (check `composer.json` `require.php`, if declared, or the PHP versions tested in `wordpress-plugin.yml`) and the corresponding compatible `yoast/phpunit-polyfills` major.
- [ ] 3.5 Bump `phpunit/phpunit` and `yoast/phpunit-polyfills` together in `composer.json` to the versions chosen in 3.4, run `composer update phpunit/phpunit yoast/phpunit-polyfills`, and verify `composer test` / `phpunit --no-interaction` passes; if incompatible, fall back to the latest `^9.x` PHPUnit release and matching polyfills version and note the constraint.
- [ ] 3.6 Regenerate and commit the updated `composer.lock` reflecting all bumped packages, and verify `composer validate --strict` passes.

## 4. Full verification

- [ ] 4.1 Run the full local/CI test pipeline (lint, phplint, `composer test`/PHPUnit, i18n check) against the updated dependencies and Actions, and verify all `wordpress-plugin.yml` jobs pass.
- [ ] 4.2 Verify the `release.yml` build step still produces the plugin zip successfully (e.g., via a dry run or by inspecting the workflow run logs on the PR), confirming no dependency or Action bump broke packaging.
- [ ] 4.3 Document any dependency deliberately left on a fallback version (per 3.3/3.5) with a short rationale in the PR description, so the deferred major bump is discoverable later.
