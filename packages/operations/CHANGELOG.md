# Changelog

## Unreleased — package identifier alignment

- Breaking before 1.0: Rename config/publish identifiers to `corex-operations` and commands to `corex:operations:*`. The tenant migration tag publishes both idempotency and tenant schema migrations.

## Unreleased

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
