# Changelog

## Unreleased — access checks and retention

- Deprecate `CoreX\Departments\Laravel\DepartmentQueries::contains()` for new callers. The SPI remains available with the original parameter names and boolean result. Use `DepartmentQueries::subtreeIds($path)->where('id', $id)->exists()` for new containment checks.
- `corex:messaging:prune` and `corex:messaging:prune-pubsub` accept `--batch` from 1 to 10000 (default 1000) to keep statements within the database parameter budget.

## CoreX 2.0 — Scope errors

- Keep `ScopeContextMismatch` and `ScopeStale` as separate final coded errors. Recovery callers handle both explicitly when they support both causes.
- Add the optional `FieldUseSnapshot` SPI for coherent authorized metadata reads.

## Unreleased — CoreX 2.0 lifecycle and ownership

- Add typed lifecycle plan, inspection and apply contracts with persisted digests, blockers, cleanup receipts and resume cursors. Compose the public Packman lifecycle binding with current operator authorization and reference admission fencing.
- Keep module closure generations and admitted reference targets in the independent scope control store. Destructive transitions require complete owner inventory, current external exclusions and retained effect evidence.
- Add the optional `ModuleTableReferences` probe for native module tables. A completed purge records the verified table drop and original cleanup receipts so later scans can account for that owner's inventory.
- Move development surface scanners from `Attributes/Tooling` to `Foundation/Tooling` and remove the obsolete kernel module entries.

## Unreleased — durable messaging and provider receipts

- Pass the caller's active Laravel connection to `Outbox::append` and `Inbox::claim`. The v2 envelope carries a typed scope, subject and correlation. Domain handlers consume each immutable fact once per logical subscriber. A changed payload under an existing fact identity raises a conflict.
- Broker workers verify stored provenance, restore the producer scope and resolve the publisher inside that frame. An expired or cancelled claim cannot acknowledge a later delivery. The health command reports quarantined rows.
- Receive broker deliveries through `BrokerReceiver` using the original restricted reference. The host retains malformed or unknown messages with their raw digest until operator release.
- Provider owners reserve a stable effect key in the independent control journal before sending. Confirmed observations survive tenant rollback and restore. Hosts authorize operator recovery through `ProviderRecoveryAuthority` and register a `ProviderReceiptProjector` for each supported owner.

## Unreleased — CoreX 2.0 trusted work scope

- Build a v3 `DataScope` from the authenticated principal with `DataScopeFactory`. `WorkScopeRunner` verifies direct work, and `StoredWorkRunner` restores work from a verified queue envelope or durable owner row. Hosts declare physical binding verification, admission storage and service principals. Old context restorer and unrestricted system authorization bindings have been removed.
- Use the shared `FieldSelection` and typed field use for record authorization. `FieldUsePolicy` describes which records support an entity-wide operation.
- Schedule a `WakeupRequest` with the full scope, handler version and normalized window. Cancellation takes the v3 scope and `WakeupIdentity`.

## Unreleased — Packman governance

- Remove 25 unused generic/module/schema error identities now owned by Packman; retain only emitted CoreX domain identities.

- Reject own-package Internal types in public/SPI signatures as well as foreign Internal types; recognize explicitly private implementation classes outside Internal namespaces.
- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.
- Remove duplicate generic attributes, configuration helpers and error-registry machinery. Use Packman error URNs and `packman:config:validate`; retain CoreX domain options, errors, context and audit adapters. Native Packman writes API manifests while the CoreX scanner retains whole-graph checks.
- Delegate provider composition to focused registrars and keep service providers within 80 lines.


## Unreleased — Packman runtime

- Breaking before release: remove unused deprecated `CoreOptionsParser::current()`; resolve the shared `CoreOptions` instance from the container.

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — invocation contract in the kernel (audit 2026-10-08 P-02)

- Add `CoreX\Invocation\Contracts\OperationHandler` (SPI implement), `RequestContext` and `CoreX\Invocation\Data\InvocationMetadata`, moved from axiomasoft/corex-operations so product modules ship operation handlers against the kernel only (docs/architecture/decisions/2026-10-08-invocation-kernel-contract.md).
- Add `CoreX\Context\Contracts\Principal` (SPI implement: `principalType()`, `principalId()`) and `CoreX\Context\Enums\PrincipalType`, moved from axiomasoft/corex-access: the single principal concept, used by access checks and returned by `RequestContext::principal()`.

## Unreleased — scheduling contracts completed (audit 2026-10-08)

- Add `CoreX\Scheduling\Contracts\DueWakeupSweeper` and `WakeupScheduleHint` (with `TAG`), moved from axiomasoft/corex-scheduler so consumers depend on the kernel only.
- Add `CoreX\Context\Laravel\WorkspaceScopeProvider` (SPI implement): names the workspace-isolation global scope; axiomasoft/corex-tenancy binds it, unbound means no workspaces.

## Unreleased — kernel audit repairs (2026-10-08)

- Preserve proven PostgreSQL module schema ownership in template and explicit-set migrations through the same transactional claims boundary as lifecycle; install the tenant registry prerequisite for explicit module selections.


## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).
- Remove verified unused Internal helpers: `ModuleDependency::phpExtension`, `DefaultSettingDefinition::workspaceScoped`, `DefaultSettingDefinition::userScoped`, `Filament::pages`, `Filament::widgets`, `Filament::tab`, `Menu::group`, `Menu::route`, `Menu::badge`, `Routes::web`, `Workflow::stepType`, `OpenTelemetryBridge::record`.
- `OpenTelemetryBridge::record()` was namespace-exported by the historical unpublished BC base; its exact removal is admitted by D130 before release. Current Api/Spi manifests do not export this Internal helper.

## Unreleased — foundation typing (P09.1, D126)

- Refine the serialized PHPDoc contracts of CompiledModule, CompiledRegistry, EntityField, EntityDefinition, FieldDefinition and ModuleManifest; validate raw compiled payloads at deserialization. Native public signatures and schema remain unchanged. Feature flag JSON payloads retain scalar, list and map round-trips.

## Unreleased — tenant baseline v1

- Tenant schema baseline v1: one file per table, explicit constraint/index names, bigint counters, and folded actor checks; the temporary `tenant-modules` set is removed.

## Unreleased — deterministic schema (P08.2)

- Remove configurable identifier strategies and the twelve table-name options. Models generate UUIDv7 with Laravel HasUuids; migrations and queries use package-owned table literals. Remove IdStrategy, IdColumns, HasConfiguredId and the unused ResolvesCoreOptions schema bridge without aliases. Native Eloquent insert assigns IDs during quiet synchronous audit writes.

## Unreleased — scheduling contracts (P07.5)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.
- Add the schema registry, migrator, scope/lifecycle DTOs, commands and testing trait; remove provider schema-version constants. Add coded schema errors.

- Added kernel module `CoreX\Scheduling`. Moved `WakeupScheduler`, `DueWakeupSource`, `DueWakeupHandler`, `WakeupRequest`, `WakeupWindow`, `WakeupDelivery`, `WakeupHandlerDefinition`, `ScheduleResult`, `CancelResult` and `SweepResult` from `CoreX\Scheduler` with no aliases. `DueWakeupSource::TAG` is `corex.scheduling.due-sources`; `WakeupHandlerDefinition::TAG` is `corex.scheduling.wakeup-handlers`.

## Unreleased — PLAN3.P07.3 mandatory synchronous audit

- Added the `SynchronousAuditWriter` SPI and stock committed database writer for mandatory support-access events. It bypasses optional audit disablement and queues, rejects ambient transactions, and requires an event-time snapshot.

## Unreleased — PLAN3.P07.2 tenancy implementation cleanup

- TenantDatabaseLifecycle documentation refers to the physical database implementation; the kernel contract
  signatures and boxed implementation are unchanged. Module connection documentation names TenancyManager.

## Unreleased — CloudEvents wire (P06.3)

- CloudEvents 1.0 structured JSON codec with internal/external profiles; MessageContract dataSchema, MessageEnvelope source URI-reference validation and withPayload. MessagePublisher wire uses application/cloudevents+json (PLAN3.D90/D92).

## Unreleased — ports instead of facades, helpers and static state (P05.3)

- Runtime code outside the Laravel bridge takes `ConnectionResolverInterface`, `ClockInterface`, `Dispatcher` and the other `Illuminate\Contracts` ports through constructors instead of facades, global helpers and static clocks; `Messaging\Internal\PubSubManager` is gone (the `PubSub` binding selects the driver from `CoreOptions::pubsubDriver`). Added the `CoreX\Database\Laravel\ResolvesCoreOptions` SPI trait for migrations and models that read `CoreOptions` through the container bridge.

## Unreleased — thin providers and registrars (P05.2)

- `CoreServiceProvider` is the only public provider of the kernel: it registers `ModulesServiceProvider` itself (after the kernel bindings, so module-aware services refine them) and delegates bindings to per-module registrars (`Config`, `Context`, `Data`, `Messaging`, `Settings`, `Audit`, `Features`, `Departments`, `Storage`, `Time`, `Observability`, `Errors`, modules). Apps no longer need to list `ModulesServiceProvider` separately. `modules:repair` is a regular command class.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Removed the static `CoreX\Config\Internal\Config` holder: `CoreOptions` is a lazy shared singleton read once at boot (`app(CoreOptions::class)` in migrations and tests; call `forgetInstance(CoreOptions::class)` after writing `corex` config in a booted test). Added `CoreOptions::$acknowledgedDangerousModes` (`corex.acknowledged_dangerous_modes`, exact config paths only), the `RuntimeConfigCheck` SPI and `RuntimeConfigGate`: checks run when the HTTP application finishes booting or a console command starts, except build commands. `packman:config:validate` runs them too, accepts `--production`, lists acknowledged dangerous modes as warnings and reports the `corex-operations.require_physical_tenancy=false` opt-out as a notice. `CoreOptionsParser::current()` is deprecated and returns the shared `CoreOptions`.

## Unreleased — CI ownership and test isolation repair (P10.2)

- Added `Messaging\Laravel\InboxRetention` for explicit cleanup of a consumer's selected inbox message IDs. Other consumers and IDs remain retained; callers must establish that the selected messages cannot be replayed.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): CoreX owns the kernel feature namespaces under one PSR-4 root; Api/Spi attributes, CoreOptions, ExtensionContribution and coded error descriptors declare the supported kernel and extension contracts. Runtime models and scanners no longer grant an implicit public contract.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — P04.2

- Defined the API and SPI through scalar contracts, JSON documents and `CoreOptions` snapshots. Concrete implementations belong to `Internal`.


## Unreleased — source API metadata (PLAN3.P04.1)

- Add final readonly `Packman\Attributes\Api` and `Spi` metadata and the `SpiKind` backed enum.
- Add a source-only API/SPI tooling scanner and deterministic `php-api/1` manifests, with nested PHPDoc, named-parameter and inheritance diagnostics.
- The report command exposes classification debt; existing namespace/package gates remain required until the strict cutover. Parser dependencies remain development-only.


## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Move module configuration into `modules` within `config/corex.php`; publish it through `corex-config`, combine kernel migration tags, and rename the PubSub prune command to `corex:messaging:prune-pubsub`. Module cache identifiers use `modules`.

## Unreleased

- Public promoted properties are captured even when their constructor is private. Runtime entity registry bindings now use the shared Internal implementation while the Testing SPI keeps its signatures. Parser and analyser dependencies are declared as optional development tooling.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema. Source scanners move to the Attributes/Errors Tooling bridges and the durable payload checker moves to Messaging/PHPStan; runtime references to development bridges are forbidden.

- Added package-contributed configuration reports through `ConfigReportSection` and public messaging test fixtures. Saved Views default bindings now register with panels.

- Added immutable `DataScope`, `RecordRef`, and `WriteToken` boundary DTOs for optional data services.

All notable changes to `corex/core` are recorded here following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, minor releases may introduce breaking changes to public contracts (D17).

## [Unreleased]


- Tenant schema baseline v1: one file per table, explicit constraint/index names, bigint counters, and folded actor checks; the temporary `tenant-modules` set is removed.

- CloudEvents 1.0 structured JSON codec with internal/external profiles; MessageContract dataSchema, MessageEnvelope source URI-reference validation and withPayload; MessagePublisher wire now uses application/cloudevents+json (PLAN3.D90/D92, P06.3).

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema. Source scanners move to the Attributes/Errors Tooling bridges and the durable payload checker moves to Messaging/PHPStan; runtime references to development bridges are forbidden.

### Changed

- Replaced closure-based `DataScope` authorization with serializable `DataScopeAuthorizer` contract (P12.8, G-API/D41).

### Fixed

- Scoped storage rejects recursively encoded backslashes/NUL and replaced local
  root symlinks, including directory listings and usage measurement.

- Feature definitions isolate duplicate inserts in a savepoint, preserving outer
  PostgreSQL transactions and administrator toggles. Database-poll publishers
  serialize per channel until commit so durable cursors cannot skip pending rows.

### Added

- Exposed durable scope and account/workspace context types as a closed public signature graph; added the `IdentityPasswordRehasher` owner port. The guarded restorer implementation remains private.

- Declared the exact shared configuration support types and tenancy contracts used by package composition as public API; no Support namespace wildcard.

- `TenantStorage` with scoped durable/ephemeral local disks, explicit usage capability and an
  explicit unsupported-export outcome. The boxed adapter derives its root exclusively from the
  current account context and does not require tenancy, auth, Stancl, cloud storage, or archives.

- **`ImpersonationService` contract + `ImpersonationState` + boxed `NullImpersonationService`
  (P2.11, B-11 §5.9, D139/D140).** Lives in `corex/core` — the sole consumer, `CurrentActor`,
  is level 0 and must not import `corex/tenancy`; the cloud implementation rebinds via
  `Config::impersonationServiceClass()` (D10). `ImpersonationGuard` (L3 — semantic layer of the
  read-only invariant, D141) + `ImpersonationRestrictedAction` enum (closed, five cases §5.9) +
  `ImpersonationRestrictedException`. `ActorType::Support` added (D137) — `sys_audit_actor_ck`
  widened by a new additive migration (closed P1.8 file untouched). `CurrentActor` now resolves
  the impersonation contract from the container: `type()`/`id()`/`context()` distinguish a
  support actor from `user`/`system`.
- Added the static factory `FeatureFlagDefinition::make()` and fluent `module()` and `payload()` setters, following `SettingDefault`. Existing constructor calls continue to work (P3.6).

### Changed

- Made the level 0 package description and README product-neutral (D91). The Composer description changed from “for the Flex ecosystem” to “for any Laravel application” (P2.17).
- Moved specification references (B-10 sections) from public `Contracts/**` docblock prose to trailing `@internal spec` tags. Documented additive module registration and dynamic disable through the `corex/modules` lifecycle state machine and a worker restart in `ModuleRegistrar` (P3.6).

## [0.1.0] - 2026-07-15

First tagged release. It implements the kernel primitives in roadmap phase P0 and blueprint B-10.

### Requires

- Tenant databases require **PostgreSQL 16**. The `sys_*` migrations use `ltree`, BRIN indexes, `jsonb`, `UNIQUE NULLS NOT DISTINCT` and `pg_advisory_xact_lock`. SQLite tests (`composer test`) exercise pure PHP code. PostgreSQL is the supported production database. Integrity and concurrency guarantees are tested with `composer test:pg` (OQ-12).

### Added

- Added `IdStrategy::Uuid7` as the default identifier strategy, using a time-sortable native PostgreSQL `uuid`. The configurable `ulid` and `int` alternatives remain available in this release (P1.1, P1.20).
- Added `TenantContext`, `AccountRef` and `WorkspaceRef` contracts for the tenancy model planned for P2 (P1.12).
- Settings v2 adds namespaced, scoped `sys_settings`, sensitive-value handling and change events (P1.7), plus scope inheritance and payload fallback (P1.22).
- Audit v2 adds queued `AuditEntry`, `sys_audit_log`, an automatic observer, sensitive-column filtering, a write-time kill switch, a monotonic `seq` cursor and an extension point for retention (P1.8, P1.21).
- `DomainEvent` replaces the empty `CoreEvent` marker and carries the event ID, timestamp and tenant. `Cancellable` is a separate optional interface (P1.9, P1.25).
- Feature flags now use persistent `sys_feature_flags` and `sys_feature_overrides` (P1.9, P1.22).
- Added `DepartmentRepository` for `sys_departments` (`ltree`), with lock-then-read `move()`, after-commit domain events and soft deletion (P1.10, P1.23).
- Added the PubSub contract, `redis`, `db-poll` and `sync-null` drivers, runtime validation, a cursor and a retention extension point (P1.11, P1.24).
- Added `CrossWorkspaceMoveException` to guard `Departments::move()` (P1.29).

### Changed

- Removed `CoreX\Registry\EntityRegistry` and `CoreX\Contracts\EntityRegistry` in favor of `EntityRegistry` v2 in `flex/modules`. See that package's changelog for this pre-1.0 breaking change (P1.2).
- `IdColumns` produces native PostgreSQL types for the configured strategy (P1.20).

### Fixed

- Audit filters marked attributes before writing to `sys_audit_log` and checks its kill switch at the point of persistence (P1.21).
- `Departments::move()` checks the new parent's `workspace_id` under the existing advisory lock before moving a subtree, preventing silent cross-workspace moves (M-2, P1.29).

### Deprecated / Will break in 0.2

- **Row-level tenancy.** `CoreX\Concerns\BelongsToTenant`, `CoreX\Scopes\TenantScope` and `CoreX\Contracts\TenantService` implement optional row-level isolation. They remain available in 0.1.0 for existing installations, including boxed applications that bind `TenantService`. Version 0.2 is planned to replace them with physical database-per-account tenancy (P2, ADR-004, stancl/tenancy v4). Avoid building new features on the deprecated contracts.
- `AuditEntry`, `SettingsScope` and `TenantContext` were expanded in this release. Their shape is provisional pending further P2 changes.
- **Integer identifiers.** Each account database has an independent sequence. Avoid the `int` strategy for new tenant-scoped models.

### Known limitations

- `FeatureFlags::findOverride()` matches one scope exactly. Inheritance from narrower to wider scopes is deferred until P2 tenancy.
- On models with `$guarded=[]`, sensitive columns other than `password` and `remember_token`, such as `api_token` and `two_factor_secret`, can enter the audit unless the host declares `auditSensitive()`.
