# Changelog

## Unreleased

### Added

- Initial opt-in `corex/api` package: explicit compiled module operations, immutable runtime declarations, per-request active-module gate and versioned dispatch.
- Public interfaces for authenticated principals, trusted request contexts, authorization, module-owned handlers, response serialization and schema contribution. Authentication and authorization default to deny; the default context requires initialized physical tenancy.
- Canonical data/error/meta envelope, request IDs, safe error mapping and an optional error boundary for the surrounding middleware pipeline.
- Deterministic OpenAPI 3.1 projection from compiled operations. Resource metadata does not create implicit CRUD.
- Standalone package tests, PHPStan and CI coverage floor of 80%. Manual provider registration only; no automatic routes, module enable or migrations.
