# Installation

## Requirements

| Component | Version |
| --- | --- |
| PHP | 8.4 or 8.5 with `mbstring`, `intl`, `bcmath`, `curl`, `pdo` and the driver you use |
| Laravel | 13 |
| PostgreSQL | 16 or newer (CI runs 16 and 18); required by tenancy, PostgreSQL-only stores and the concurrency features |
| SQLite | Enough for the default test lane and for packages without PostgreSQL features |
| Meilisearch | Optional, only with `axiomasoft/corex-search-meilisearch` |

## Ecosystem dependencies

CoreX builds on Packman, Tenant, AzGuard and Vaulter. These are not on
Packagist yet: the root `composer.json` declares inline `type: package`
repositories pinned to exact Git commits, and `composer.lock` records the
resolution. The pins and the procedure for a local checkout of an upstream are
in [ecosystem dependencies](architecture/ecosystem-dependencies.md).

An application that consumes CoreX needs the same repository entries until the
packages are published.

## Add CoreX to an application

```bash
composer require 'axiomasoft/corex:^2.0@dev'
php artisan vendor:publish --tag=corex-config
php artisan vendor:publish --tag=packman-modules-config
```

Add the optional packages you need, for example
`axiomasoft/corex-access`, `axiomasoft/corex-tasks` or
`axiomasoft/corex-webhooks`. Each package README lists its publish tag,
configuration key and environment prefix.

## Install the schema

Package migrations are not published. Packman Schema installs them from the
registered schema sets:

```bash
php artisan packman:schema:migrate --scope=tenant --database=<tenant>
php artisan packman:schema:status --scope=tenant --database=<tenant>
```

Production needs `--force`. With tenancy, the central database is installed
with `--scope=central --database=<central>` and the dedicated budget connection
with `--scope=dedicated`. Modules create their own tables when
`Packman\Modules\Contracts\Lifecycle\ModuleLifecycle` activates them. Sets,
drivers and shape rules are in the [schema policy](architecture/schema-policy.md).

## Work on the monorepo

```bash
git clone https://github.com/axiomasoft/corex.git && cd corex
composer install
composer test
```

`scripts/php84` selects PHP 8.4 for Composer and child processes when several
PHP versions are installed. See [Testing and CI](testing-ci.md) for the
PostgreSQL lane and the standalone package installs.
