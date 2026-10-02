# Changelog

## Unreleased — typed Options and runtime configuration checks (P05.1)

- `ApiOptions`, `BulkOptions` and `CostOptions` are lazy shared singletons resolved in `boot()`; the idempotency store takes lease and retention from `ApiOptions`. Added a `RuntimeConfigCheck` that requires `app.key` in `production`.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): The package uses CoreX\Operations as its sole PSR-4 root; authorization, operation catalogue, bulk execution and validation expose feature-owned ports and typed inputs/results. Extracted tools, webhooks, custom fields and transfer APIs belong to their own packages.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — P04.2

- Публичные JSON-поля операций и результатов закрыты JsonDocument; интеграционные парсеры отделены от неизменяемых DTO.


## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Rename config/publish identifiers to `corex-operations` and commands to `corex:operations:*`. The tenant migration tag publishes both idempotency and tenant schema migrations.

## Unreleased

- Include public promoted resource and stored-outcome fields even when their constructor is private; source signatures stay unchanged.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- Extract webhook provider/config, snapshot section and bounded `corex:webhooks:prune`; the engine uses the snapshot SPI and owns only bulk/budget retention.
- Add public request-context and invocation testing factories; preserve transport assertions in separately owned test cases.


### Added

- Initial opt-in `corex/api` package: explicit compiled module operations, immutable runtime declarations, per-request active-module gate and versioned dispatch.
- Public interfaces for authenticated principals, trusted request contexts, authorization, module-owned handlers, response serialization and schema contribution. Authentication and authorization default to deny; the default context requires initialized physical tenancy.
- Canonical data/error/meta envelope, request IDs, safe error mapping and an optional error boundary for the surrounding middleware pipeline.
- Deterministic OpenAPI 3.1 projection from compiled operations. Resource metadata does not create implicit CRUD.
- Standalone package tests, PHPStan and CI coverage floor of 80%. Manual provider registration only; no automatic routes, module enable or migrations.
- Tool descriptor projection (`ToolDescriptor`) and bounded offline `tool-schema/1` validation (`ToolSchemaValidator`, `ToolSchemaSupervisor`, optional Opis adapter). Canonical `JsonObject`/`JsonValue` wrappers preserve object/array distinction and safe integer bounds.
- Current-policy tool catalogue (`ToolCatalogue`, `CompiledToolCatalogue`, `ToolCatalogueView`) with fresh module activation and consumer `ToolPolicy` filtering, deterministic sorted revision, 1000-descriptor cap and default deny policy. Register via explicit `ToolServiceProvider`; no authority cache or routes.
- Guarded headless invocation (`ToolInvoker`, `GuardedToolInvoker`, `ToolCall`, `ToolResult`, `ToolRequestContext`) with schema validation, contract-hash checks, additive tool metadata on trusted context and `ToolExecutionGuard` seam. Default `ReadOnlyToolExecutionGuard` admits read tools only; consumer guards own approval, idempotency and mutation policy.
- Fake MCP transport parity via `Internal\McpToolsProjection` (`mcp-tools-projection/1`, protocol `MCP2025-11-25`) mapping tools/list and tools/call onto the same catalogue and invoker without installing routes or live endpoints.
- Installed consumer qualification tests (`InstalledToolContractTest`) and delivery `tool-install-qualification-20260915/` cold ZIP install verifier.
- Tenant-local idempotency persistence contracts (`IdempotencyStore`, `IdempotencyScope`, `IdempotencyAttempt`), fenced SQL lease/outcome storage, finite terminal retention and an opt-in `corex-api-migrations-tenant` publication tag.
