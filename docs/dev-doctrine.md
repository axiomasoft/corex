# CoreX development rules

## Package boundaries

CoreX packages serve Laravel applications independently of CRM, CMS and Flex products. Products depend on packages. The kernel `axiomasoft/corex` has no dependency on sibling packages. A package keeps its code and tests in `packages/<name>/`. Products live in `products/`. Root tests cover integration, architecture and shared quality gates. Composer manifests and `scripts/package-graph-gate.json` specify dependency rules. Import only another package's public contracts.

Packman owns the module, schema and panel runtimes. Tenant owns isolation and physical resources. AzGuard owns its authorization engine. CoreX supplies account and workspace adapters, application policies and host contracts. Chatom handles conversation messages and the CoreX outbox handles domain events. Create a separate integration package when a consumer needs it. [Package map](architecture/package-map.md)

## Public contracts

Review changes to APIs, SPIs, service providers, schema and exported behavior for compatibility and record them in the affected package CHANGELOG. Commit `resources/contracts/php-api.json` and Golden snapshots. A stable release also requires an independent compatibility comparison against an approved baseline. [API policy](architecture/api-policy.md) · [Version policy](architecture/semver-policy.md)

The domain kernel uses the `corex` configuration namespace and `corex-config` publish tag. Packman supplies module runtime configuration (`modules`, `PACKMAN_MODULES_*` and `modules:*`). `flex/crm` identifies the product.

## Modules and schema

A module declares Composer type `packman-module`, extends `Packman\Modules\Contracts\Definition\ModuleDefinition` and sets `extra.packman.module`. Composer metadata supplies its version. A module rename requires data migration.

Extension mechanisms are events, extenders, components, capabilities and dependency-injected contracts. The closed extender set is Entity, EntityField, Permissions, Api, Filament, Menu, Routes and Workflow. Extending this set requires an architecture decision. CoreX integrates Packman's compiler, registries and lifecycle through public contracts. Its adapters add account context, audit and feature flags. Packman supplies shared attributes, configuration validation and the problem registry. CoreX supplies domain Options and error definitions. Providers use `WithSchema`, `OwnsSchema` and `schemaSetProviders()` and fit within 80 lines. Compiler tests can require upstream Internal test seams while those seams lack a public alternative.

Declare `Packman\Schema\Data\Migration\SchemaSet` through `Packman\Schema\Contracts\Migration\SchemaSetProvider`. Packman's `SchemaMigrator` executes the sets. Packman Modules owns `mod_*` control tables in `axiomasoft/packman-modules:tenant`. CoreX owns only its domain tables. Use `packman:schema:status` and `packman:schema:migrate` with an explicit scope and supply `--force` in production. Packages keep migrations in their source sets. [Schema policy](architecture/schema-policy.md) · [Table ownership](architecture/table-ownership.md)

## Verification

The development matrix covers PHP 8.4 and 8.5 with Laravel 13.

```bash
scripts/php84 composer lint:check
scripts/php84 composer analyse
scripts/php84 composer test
scripts/php84 composer test:pg
```

Run the affected package suites, architecture checks and relevant API and schema gates. Run PostgreSQL tests in an isolated test database. SQLite and PostgreSQL exercise different behaviors. Root line coverage uses the configured 80% floor for its source directories. Packages may require higher coverage. CI checks the mutation inventory for changed code. Dispatch a mutation run to execute the full fragments. [Testing and CI](testing-ci.md)

## Local tooling

Keep private plans, audit scratch files, generated reports and per-user development tools outside package sources. Local tooling can be installed through swissknifeman. Keep third-party skills in their connector. Machine-specific settings are listed in `.gitignore`.
