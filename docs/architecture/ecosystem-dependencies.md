# Ecosystem dependencies

CoreX uses the public contracts of Packman, Tenant, AzGuard and Vaulter. The root Composer repository pins the Packman snapshot to `0.1.2`. CoreX requires `packman-modules` `^0.1.1` for its public compilation contract. The reviewed development snapshots for Tenant, AzGuard and Vaulter satisfy `^1.0@dev`, `^2.0@dev` and `^1.0@dev`, respectively. Separately verify that the environment can fetch each source commit.

## Pinned sources

The root `composer.json` contains inline repositories of `type: package`. The root `composer.lock` records resolved versions. Each snapshot identifies the exact Git commit and PSR-4 paths inside the owner's monorepo. Those paths and the child package metadata are needed for Composer to discover the packages inside a monorepo.

| Owner | Packages | Source reference |
|---|---|---|
| Packman (`0.1.2`) | `packman`, `packman-modules`, `packman-schema`, `packman-panels`, `packman-plugins`, `packman-devtools`, `packman-testkit` | `d4eaf2ad01d22a822368765d154ae51ff70981f2` |
| AzGuard | `azguard` | `42bdb639d3ab4ea2571c3026113cac7727c88b18` |
| Tenant | `tenant`, `tenant-laravel` | `6325c62d5a9c0afe1f7bce769b32b7b24c726294` |
| Vaulter | `vaulter-core`, `vaulter-media` | `b230f6e27b53c92145fa81be2fdeb89ea5c8ae03` |

All package names in the table use the `axiomasoft/` prefix. Packman is pinned to `0.1.2`. The other packages use `dev-main` and their owner's numeric branch alias for dependency resolution. A branch alias alone does not establish that the remote default branch contains the commit. The pinned AzGuard source URL points to the [AzGuard repository](https://github.com/axiomasoft/azguard). The reviewed commit was also available through [PR 131](https://github.com/axiomasoft/azguard/pull/131).

Source metadata serves child-package discovery regardless of repository access controls. A developer can install using a local checkout or cache before the source is available to a clean CI worker. Reproducible CI requires access to the referenced commit.

For authorization, an application can require `axiomasoft/corex-access`. Composer installs the engine transitively from the pinned snapshot. Use the CoreX `AccessChecker` to check permissions and `ScopeResolver` to restrict queries.

## Working in the monorepo

With all source commits available, run the following from the repository root.

```bash
scripts/php84 --check
scripts/php84 composer install --prefer-source
```

The CoreX packages and product install from the relative `packages/*` and `products/*` directories. External snapshots can be installed independently of sibling directory layouts. For a local Vaulter source, obtain a checkout containing the required commit or import a supplied Git bundle into a separate repository. Check for the commit without changing the checkout.

```bash
git -C /absolute/path/vaulter cat-file -e b230f6e27b53c92145fa81be2fdeb89ea5c8ae03^{commit}
```

To use that repository for just this installation, map the Vaulter source URL through process-local Git configuration. This preserves the Composer manifest and lockfile.

```bash
env \
  GIT_CONFIG_COUNT=1 \
  GIT_CONFIG_KEY_0=url.file:///absolute/path/vaulter/.insteadOf \
  GIT_CONFIG_VALUE_0=https://github.com/axiomasoft/vaulter.git \
  scripts/php84 composer install --prefer-source
```

Substitute the actual absolute path. If the environment already uses `GIT_CONFIG_COUNT`, append a new rule and preserve its existing entries. Keep machine-specific paths, tokens and credentials out of Composer metadata. Before relying on a clean installation, either publish the patch upstream or supply CI with a verified Git bundle.

## Installing one package for development

The root `composer install` covers ordinary monorepo checks. Use the shared wrapper when a check requires the package's own `vendor` directory.

```bash
scripts/php84 python3 scripts/composer-with-snapshots.py --working-dir=packages/media update --prefer-source --no-interaction
scripts/php84 python3 scripts/composer-with-snapshots.py --working-dir=packages/media audit --locked
```

The wrapper preserves the package's repository declarations and adds repositories from its parents, following the same inheritance rules as the source audit. Local path dependencies must lie inside this checkout. An external development dependency must match the reviewed root lock's source and package metadata. Global `minimum-stability` remains `stable`. To meet Composer's root stability requirements, the wrapper adds `@dev` only for reviewed owner snapshots reachable through `require`. Unrelated optional integrations stay outside the install.

During `update`, PHPStan, strict rules and Larastan use the exact versions in the root lock when the package requires them. Root CI uses the same tool versions. Packman devtools and testkit also belong to the reviewed toolchain and are pinned only when reachable.

The wrapper writes ignored `.composer-snapshots.json` and `.composer-snapshots.lock` files alongside the source manifest. It preserves the source `composer.json` and `composer.lock`, and keeps `vendor` and autoload paths local to the package. Run subsequent `install`, `validate` and `audit --locked` commands through the same wrapper. Package Composer scripts use the installed `vendor` directory. Exclude generated manifests and locks from release artifacts because they contain local paths.

The Octane consumer harness follows this installation process. Its source manifest retains the exact PHPUnit constraint. The obsolete lock predating the Packman split was removed.

## Consumer applications and distribution

Composer uses repository declarations from the root package. An application must configure an approved CoreX channel together with the external package snapshots, or an accessible owner channel that provides their equivalents. Keep monorepo `path` repositories out of application configuration. For a temporary snapshot channel, copy the complete `type: package` entries, including `source`, `require`, `autoload`, providers and branch aliases. A commit alone cannot describe a child Composer package.

Once the channel is available, select the required packages, for example with `composer require 'axiomasoft/corex:^2.0'`, and retain the application's lockfile. CoreX split artifacts contain CoreX source only. Distributors supply the external Composer channel separately. The consumer fixtures register owner snapshots after the candidate repository and require access to the same Git sources.

When updating an owner, change the inline metadata and lockfile together, then check the adapter API and its integration tests. Before publishing CoreX, verify that each source reference is available to the target environment. Distribute any local patch separately or replace it with a verified, accessible upstream commit.

## Shared Packman checks

Install `packman-devtools` and `packman-testkit` for development. The root `packman.json` lists 20 packages and `products/flex-crm`. The `extra.packman` block carries taxonomy and the error prefix. CoreX domain gates also read `extra.corex`. Modules with lifecycle support retain `type: packman-module` and `extra.packman.module`. The local profiles in `schemas/profiles/` refine the Composer type and the 80-line limit for modules and the product while keeping surface checks enabled.

```bash
scripts/php84 vendor/bin/packman gate
scripts/php84 vendor/bin/packman php-api --check
scripts/php84 vendor/bin/packman error-codes --check
scripts/php84 vendor/bin/packman config-schema --check
```

Run these checks sequentially. Some architecture tests temporarily modify files to prove their checks fail. Give `composer test` exclusive access to its checkout while running those tests. Run Rector, PHPStan, Packman and source, API or graph scanners before or after that test run.

Packman generates `resources/contracts/php-api.json`. CoreX's `composer php-api` additionally verifies the complete type boundary and public API reference. Error URNs use the `urn:packman:problem:` prefix and CoreX domain error codes retain their own prefixes.

Packman's `TableWriteOwnershipGate` checks `gates.table_owners[table]` against the current Composer package. Only the recorded owner can write its `sys_*` tables. The gate rejects unknown system tables and writes to another owner's tables. DATA-003 independently verifies actual writes.

CI must be able to read all four source references via their canonical GitHub URLs or a supplied checkout or bundle. For each local source, add a distinct `url.file:///.../.insteadOf` rule to the process-local `GIT_CONFIG_COUNT`. Preserve sibling checkouts, global Git settings and Composer source URLs.
