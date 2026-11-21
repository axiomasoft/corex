# Changelog

## Unreleased — owner-scoped bulk transfer

- Added the neutral BulkExecutor and BulkOwner ports, scoped admission, replayable step tokens, and typed owner result and failure references.

## CoreX 2.0 — Durable effects

- Reauthorize operation retries in their verified logical scope. Commit successful operation receipts with their tenant effects and recover dedicated budget settlements from the same receipt.

## Unreleased — Packman governance

- Move the physical-tenancy opt-out report into the operations configuration section; expose IdempotencyStore from its factory and keep Artisan handler classes private.
- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.


## Unreleased — package boundaries

- Remove the obsolete empty `CatalogueModuleFixture::flush()` test hook; current Packman `ModuleRegistry` has no flush method.

- Rename the budget/tenant connection holder to `ApiSchemaConnections`; its public factory and connection admission checks remain intact.

- Breaking before release: remove deprecated invocation aliases and the `CostRegistrar`, `BulkRegistrar` and `ApiSchemaConnections::register()` shims. Use kernel invocation/principal contracts and the provider’s canonical bindings and HTTP registrars.

- Breaking before release: Rename the public Laravel provider to `CoreX\Operations\OperationsServiceProvider`.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — idempotency scope keyed by principal type (audit 2026-10-08)

- Security fix: `IdempotencyScope` gains `PrincipalType $principalType` (before `principalId`) and its collision domain now includes `principal_type`. Previously principals of different types with the same id (user `5` and agent `5`) shared an idempotency scope, so one could be served the other's stored response or be rejected as a conflicting replay. Stored scope hashes change; in-flight keys from before the upgrade are not replayed.

## Unreleased — invocation contract moved to the kernel (audit 2026-10-08 P-02)

- Operation handlers use `CoreX\Invocation\Contracts\OperationHandler`, `CoreX\Invocation\Contracts\RequestContext`, `CoreX\Context\Contracts\Principal` and `CoreX\Invocation\Data\InvocationMetadata` from `axiomasoft/corex` directly.
- Breaking (unpublished): the operations principal is now the single kernel principal, so `Principal::id()` becomes `principalId()` and implementations add `principalType(): PrincipalType`. `PrincipalResolver` implementations can return access's `PrincipalIdentity` directly.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — baseline v1

- Correct JSON decoder and schema budget PHPDoc to preserve numeric object member names and recursive schema lists; native signatures and accepted JSON values remain unchanged (PLAN3.D128).

- Схема: набор(ы) `tenant` и `budget` (Dedicated), baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — schema set connection ownership

- Shipped migrations obtain their `up()` connection from the schema migrator's current connection. Test retention fixtures use `SchemaMigrator` instead of calling migration `up()` directly. DDL is unchanged.


## Unreleased — resource identifier formats (P08.2)

- ResourceId uses operations-owned ResourceIdFormat instead of the removed kernel schema strategy. Rename its strategy property/parameters to format and remove fromConfigured; callers supply the expected format explicitly. UUIDv7, UUID, ULID and integer wire domains remain supported.

## Unreleased — strict input envelopes and RFC 9457 errors

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Breaking before 1.0: operation errors always use `application/problem+json` with registry-owned type/title and occurrence detail; successes contain only data and meta. Remove Accept negotiation and the configurable problem type base.
- Add the public InputEnvelope enum and Operation inputEnvelope policy derived by OperationFactory; reject invalid envelopes, unsupported media types and undeclared fields with distinct registered codes.

## Unreleased — ports instead of facades, helpers and static state (P05.3)

- Services take `ConnectionResolverInterface` and `ClockInterface` through constructors instead of facades and helpers. `Testing\MutatingHandler` now needs a connection resolver and a clock.

## Unreleased — thin providers and registrars (P05.2)

- `OperationsServiceProvider` delegates to `OperationsBindingsRegistrar`, `IdempotencyRegistrar`, `PaginationRegistrar`, `HttpRegistrar`, `ApiSchemaBindingsRegistrar`, `CostBindingsRegistrar` and `BulkBindingsRegistrar`. The anonymous replay authorizer, cursor keyring and handler-failure holder are named classes (`DefaultReplayAuthorizer`, `ConfiguredCursorKeyring`, `HandlerFailure`); the idempotency store is built by `IdempotencyStoreFactory`.

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
- Current-policy tool catalogue (`ToolCatalogue`, `CompiledToolCatalogue`, `ToolCatalogueView`) with fresh module activation and consumer `ToolPolicy` filtering, deterministic sorted revision, 1000-descriptor cap and default deny policy. Register via explicit `ToolsServiceProvider`; no authority cache or routes.
- Guarded headless invocation (`ToolInvoker`, `GuardedToolInvoker`, `ToolCall`, `ToolResult`, `ToolRequestContext`) with schema validation, contract-hash checks, additive tool metadata on trusted context and `ToolExecutionGuard` seam. Default `ReadOnlyToolExecutionGuard` admits read tools only; consumer guards own approval, idempotency and mutation policy.
- Fake MCP transport parity via `Internal\McpToolsProjection` (`mcp-tools-projection/1`, protocol `MCP2025-11-25`) mapping tools/list and tools/call onto the same catalogue and invoker without installing routes or live endpoints.
- Installed consumer qualification tests (`InstalledToolContractTest`) and delivery `tool-install-qualification-20260915/` cold ZIP install verifier.
- Tenant-local idempotency persistence contracts (`IdempotencyStore`, `IdempotencyScope`, `IdempotencyAttempt`), fenced SQL lease/outcome storage, finite terminal retention and an opt-in `corex-api-migrations-tenant` publication tag.
