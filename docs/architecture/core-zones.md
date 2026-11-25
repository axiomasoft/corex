# Kernel zones and modules

`axiomasoft/corex` publishes the contracts shared by the application packages.
`packages/core/src/` holds `CoreServiceProvider.php` and the following modules.

| Module | Responsibility |
| --- | --- |
| `Audit` | Action audit and the log retention policy. |
| `Config` | Kernel configuration and runtime binding checks. |
| `Context` | Verified context, principal, physical binding, restore epoch and work admission. |
| `Data` | DataScope, identifiers, records, fields and their authorization contract. |
| `Database` | Owner connection, base model and model registration in catalogues. |
| `Departments` | Department directory. |
| `Errors` | Shared CoreX errors and the integration with the Packman catalogue. |
| `Features` | Application feature flags. |
| `Foundation` | Kernel composition, the public lifecycle entry and reference admission. |
| `Invocation` | Handler and transport contracts for a trusted request. |
| `Messaging` | Domain facts, durable payloads, inbox and outbox. |
| `Observability` | Tracing, metrics and operation context. |
| `Scheduling` | Contracts for scheduling deferred work. |
| `Settings` | Settings metadata and persistence. |
| `Storage` | Tenant storage contracts and the scoped runtime. |
| `Time` | Clocks and time presentation rules. |

The runtime namespace follows the PSR-4 path. The public-surface scanner and its
DTOs live in the dev bridge `CoreX\Foundation\Tooling`. Scripts use the Packman
API/SPI attributes. Rule R6 of the layout gate forbids runtime dependencies on
dev bridges.

The kernel uses Packman's public discovery and compilation. CoreX lifecycle
admission holds owner references until their transaction or effect is
confirmed. Packman Schema and Packman Modules run the schema and the actual
transitions. Saved views belong to Packman Panels, money types to Intl and
application fields to Custom Fields. The Laravel principal adapter belongs to
Access and lives at `CoreX\Access\Laravel\PrincipalIdentity`.

`CoreZoneTest` checks the zone import rules and the composition entry points.
`scripts/module-layout.json` and `scripts/ownership-map.json` define the current
package catalogue. [Layout](structure-profiles.md) · [Package map](package-map.md)
