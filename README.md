# CoreX

[![CI](https://github.com/axiomasoft/corex/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/axiomasoft/corex/actions/workflows/ci.yml)

CoreX is a modular Laravel foundation (MIT) for the Flex products (FlexCRM,
FlexCMS, FlexStore). Products compose its packages instead of rewriting tenant
context, access control, schema, auditing, messaging and the other shared
building blocks. This monorepo is where every package is developed; each
package is published as its own Composer package.

## Packages

Purposes, boundaries and naming rules: [package map](docs/architecture/package-map.md).

| Package | Purpose | Docs |
|---|---|---|
| `axiomasoft/corex-access` | Authorization, record visibility, role matrices and direct grants on AzGuard. | [access](packages/access/README.md) |
| `axiomasoft/corex` | CoreX application foundation — tenant context, settings, audit and messaging on Packman modules and schema. | [core](packages/core/README.md) |
| `axiomasoft/corex-custom-fields` | Typed attribute ports and safe schema-operation bridge for CoreX. | [custom-fields](packages/custom-fields/README.md) |
| `axiomasoft/corex-intl` | Typed locale, money/rate snapshots, and offline geography reference utilities for CoreX. | [intl](packages/intl/README.md) |
| `axiomasoft/corex-media` | Tenant-scoped private media lifecycle primitives for CoreX. | [media](packages/media/README.md) |
| `axiomasoft/corex-notifications` | Durable tenant-scoped notification primitives for CoreX. | [notifications](packages/notifications/README.md) |
| `axiomasoft/corex-operations` | Opt-in module operation dispatch, trusted context contracts and OpenAPI projection for Laravel. | [operations](packages/operations/README.md) |
| `axiomasoft/corex-panels` | Trusted CoreX context integration for Packman panels and saved views. | [panels](packages/panels/README.md) |
| `axiomasoft/corex-recurrence` | Bounded RFC 5545 recurrence expansion for CoreX consumers. | [recurrence](packages/recurrence/README.md) |
| `axiomasoft/corex-search` | Typed, policy-hydrated search boundary for CoreX. | [search](packages/search/README.md) |
| `axiomasoft/corex-search-custom-fields` | Optional projection and committed invalidation of Custom Fields in Search. | [search-custom-fields](packages/search-custom-fields/README.md) |
| `axiomasoft/corex-search-intl` | Optional localized projection glue between axiomasoft/corex-intl and axiomasoft/corex-search. | [search-intl](packages/search-intl/README.md) |
| `axiomasoft/corex-search-meilisearch` | Optional Meilisearch candidate adapter for axiomasoft/corex-search. | [search-meilisearch](packages/search-meilisearch/README.md) |
| `axiomasoft/corex-tasks` | CoreX Tasks module — workspace projects, stages and tasks for DB-per-account applications. | [tasks](packages/tasks/README.md) |
| `axiomasoft/corex-recurring-tasks` | Recurrence-to-task materialization for CoreX with durable wakeup. | [recurring-tasks](packages/recurring-tasks/README.md) |
| `axiomasoft/corex-tasks-workflow` | Optional Workflow intent capture on the first Task completion. | [tasks-workflow](packages/tasks-workflow/README.md) |
| `axiomasoft/corex-tenancy` | CoreX account and workspace lifecycle with external Tenant runtime and host-owned physical resources. | [tenancy](packages/tenancy/README.md) |
| `axiomasoft/corex-tools` | Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection. | [tools](packages/tools/README.md) |
| `axiomasoft/corex-import-export` | Bounded import and export primitives for CoreX. | [import-export](packages/import-export/README.md) |
| `axiomasoft/corex-scheduler` | Durable, tenant-local scheduling of one-shot work. | [scheduler](packages/scheduler/README.md) |
| `axiomasoft/corex-webhooks` | Webhook subscriptions, signing, delivery and replay for CoreX operations. | [webhooks](packages/webhooks/README.md) |
| `axiomasoft/corex-workflow` | Optional durable workflow engine binding for CoreX modules. | [workflow](packages/workflow/README.md) |
| `flex/crm` | FlexCRM product built on CoreX | [flex-crm](products/flex-crm/README.md) |

## Quick start

```bash
composer require 'axiomasoft/corex:^2.0@dev'
php artisan vendor:publish --tag=corex-config
php artisan packman:schema:migrate --scope=tenant
```

The ecosystem packages (Packman, Tenant, AzGuard, Vaulter) are pinned Git
snapshots until they are published; set them up first as described in
[installation](docs/installation.md).

## Stack

- PHP 8.4 and 8.5, Laravel 13.
- PostgreSQL for tenancy and the PostgreSQL-only stores; SQLite is enough for
  the default test lane.
- UUIDv7 identifiers; money in minor units via `brick/money` (`corex-intl`).
- Optional multi-tenancy: `axiomasoft/corex-tenancy` maps accounts and
  workspaces onto the external Tenant runtime.
- Authorization through the `axiomasoft/corex-access` role matrix on AzGuard.
- Public contracts follow strict SemVer ([policy](docs/architecture/semver-policy.md)).

## Documentation

The [documentation index](docs/index.md) covers installation, architecture,
configuration, usage, errors, testing and CI.

## Development

```bash
composer install
composer test          # Pest + Testbench on SQLite
composer test:pg       # PostgreSQL lane (docker compose up -d; see docs/testing-ci.md)
composer analyse       # PHPStan, max level
composer lint:check    # Pint
composer refactor:check
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow and
[testing and CI](docs/testing-ci.md) for every lane.

## License

MIT, see [LICENSE](LICENSE).
