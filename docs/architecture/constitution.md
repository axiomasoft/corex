# CoreX architectural constitution

**Version** 1.0.0 · **Status** Normative · **Level** 0 (`corex/*`)

This register defines the architectural invariants that CoreX contributors must follow. Architecture decision records explain the reasoning. Individual policies describe implementation and cite the applicable article ID. The enforcement field identifies the evidence for each rule. Rules without an automated check require review.

Each requirement has one authoritative home. Other documents cite its article ID so that readers can identify the rule that applies. See the policies on the [public API](api-policy.md), [configuration](config-policy.md), [data ownership](data-ownership-policy.md), [messages](message-policy.md), [extension points](extension-policy.md), [structure](structure-profiles.md), [naming](naming.md) and [taxonomy](taxonomy.md).

## Reading an article

| Field | Meaning |
|:--|:--|
| ID | Stable `<GROUP>-<NNN>`. A retired ID must never be reused. |
| Rule | The normative requirement. |
| Owner | This constitution or the external document that owns the rule. |
| Enforcement | The check and its cadence where known. “Review” identifies a human check. |
| Exceptions | The exception register or a dash for none. |
| Rationale | The reason for the rule, where stated. The owning policy or decision supplies the full reasoning. |

The groups are PKG (package boundaries), STR (structure), API (public surface), DATA (data ownership), RUN (runtime), CFG (configuration), MSG (messages), EXT (extensions) and NON (non-goals).

## Exceptions

An exception must identify the affected article and paths, owner, reason, compensating control, expiry date or removal condition, and the check that proves its removal. Existing code has no blanket exemption. An exception without a removal condition cannot be accepted.

Store exception records under `exceptions/<RULE-ID>/<short-name>.md`. Machine-readable exceptions already held in `scripts/package-graph-gate.json` stay there. Do not duplicate these records in Markdown.

## PKG Package boundaries

### PKG-001 — State the package purpose

**Rule.** The first line of every active `packages/*/README.md` must state in one sentence the problem the package solves. A list of technologies or classes does not meet this requirement.

**Owner.** This constitution.

**Enforcement.** Review the first line for an empty description or a list in place of a purpose statement.

**Exceptions.** —

**Rationale.** A clear purpose defines which changes belong in the package.

### PKG-002 — Dependencies point down the layers

**Rule.** The `corex/*` layer cannot depend on `flex/*`.

**Owner.** [Development doctrine](../dev-doctrine.md) §1.

**Enforcement.** On every PR, CI job `layer-gate` runs `composer test:arch`. `tests/Arch/LayerTest.php` checks “foundation packages do not import flex/* namespaces” and “foundation packages declare no upward composer dependencies”.

**Exceptions.** —

**Rationale.** Level 0 must work for projects without CRM, CMS or Store.

### PKG-003 — The kernel is independent

**Rule.** `axiomasoft/corex` cannot depend on any other monorepo package.

**Owner.** [Development doctrine](../dev-doctrine.md) §1.

**Enforcement.** `composer test:arch` checks “axiomasoft/corex declares no monorepo package dependencies” and “axiomasoft/corex source imports no sibling package namespace”.

**Exceptions.** —

**Rationale.** An upward dependency would prevent reuse of the kernel in isolation.

### PKG-004 — Declare cross-package dependencies

**Rule.** Every cross-package dependency must appear in the consuming package’s `composer.json`.

**Owner.** This constitution.

**Enforcement.** `composer package-graph-gate` checks R1 on every PR. R6 prohibits the edge from `axiomasoft/corex-notifications` to `axiomasoft/corex-scheduler`. Wakeup contracts belong to `CoreX\Scheduling`.

**Exceptions.** —

**Rationale.** Undeclared dependencies break profile-specific installations such as boxed deployments without tenancy.

### PKG-005 — Guard optional integrations

**Rule.** An absent optional integration must not break bootstrap. Guard registration with `interface_exists` or `bound` and record the integration in the gate allowlist with `guard_contains`.

**Owner.** This constitution.

**Enforcement.** `composer package-graph-gate` validates the guard on allowlisted edges.

**Exceptions.** —

**Rationale.** Boot must work both with and without the optional dependency.

### PKG-006 — Keep domain contracts with the host

**Rule.** A bridge package decorates its hosts’ contracts and cannot own their domain contracts.

**Owner.** [Development doctrine](../dev-doctrine.md) §5, bridge packages.

**Enforcement.** `composer package-graph-gate` checks R2. Review the bridge package’s `Contracts/` directory.

**Exceptions.** —

**Rationale.** A host domain must remain independent of its adapters.

### PKG-007 — Keep product literals out of foundation packages

**Rule.** PHP, JSON, Markdown and SQL under `packages/{core,tenancy,access,tasks}` must contain no `flex` literals outside the explicit allowlist.

**Owner.** [Development doctrine](../dev-doctrine.md) §1.

**Enforcement.** `composer test:arch` checks “foundation packages carry no flex product literals”.

**Exceptions.** —

**Rationale.** The layer boundary also applies to text and configuration.

### PKG-008 — Include every package in the monorepo checks

**Rule.** Every new package needs a Composer manifest, its own test suite and the applicable architecture, API and schema contract checks.

**Owner.** [Development doctrine](../dev-doctrine.md), verification section.

**Enforcement.** `composer test:packages` enumerates packages and products with `phpunit.xml`. CI coverage is defined in `.github/workflows/ci.yml`.

**Exceptions.** —

**Rationale.** New and existing packages have the same verification obligations.

### PKG-009 — Retired historical import-export directory rule

The rule for an empty historical directory was retired during the ecosystem redesign. `packages/import-export` now contains the active `axiomasoft/corex-import-export` package and participates in tests, analysis and distribution. The article ID remains retired.

## STR Package structure

### STR-002 — Keep each use case in one feature module

**Rule.** A use case belongs to one package feature module, either the root or `<Module>/`. Do not split it across layer directories.

**Owner.** This constitution.

**Enforcement.** `composer module-layout-gate` in CI and review of use-case ownership.

**Exceptions.** The ratchet in `scripts/module-layout-baseline.json` rejects new and obsolete entries. Existing exceptions must be retired as the layout is corrected.

**Rationale.** Related implementation stays inside one boundary.

### STR-003 — The package root is its root module

**Rule.** The package’s `src/` root contains its public categories, `Internal/`, `Laravel/`, `Testing/`, `PHPStan/`, `Tooling/`, service providers, `<Pkg>Module.php` and feature modules. The kernel root contains only `CoreServiceProvider.php` and the modules listed in the glossary.

**Owner.** This constitution.

**Enforcement.** `composer module-layout-gate` checks R0–R3 in CI.

**Exceptions.** The ratchet in `scripts/module-layout-baseline.json` rejects new and obsolete entries. Existing exceptions must be retired as the layout is corrected.

**Rationale.** Paths identify namespaces and module owners. The kernel module list is closed.

### STR-004 — Give directories a boundary

**Rule.** Do not create a single-class directory unless it represents a boundary.

**Owner.** This constitution.

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### STR-005 — Scope Internal to its module

**Rule.** `Internal/` lives directly under its owning module (`<Module>/Internal/`). Other packages and other modules of the same package cannot import it. Composition roots may reference Internal types from another module of the same package. These roots are service providers, `<Pkg>Module.php` and the `Laravel/` directory directly under a module.

**Owner.** This constitution.

**Enforcement.** `composer package-graph-gate` checks the cross-package boundary in R2. `composer module-layout-gate` checks R4/R5 in CI.

**Exceptions.** The ratchet in `scripts/module-layout-baseline.json` rejects new and obsolete entries. Existing exceptions must be retired as the layout is corrected.

**Rationale.** Modules own their implementations and expose explicit composition points.

### STR-006 — Place public categories directly under their module

**Rule.** Only `Contracts`, `Data`, `Enums`, `Events` and `Exceptions` are public module categories. Layer directories such as `Support`, `Models` or `Commands` are forbidden in that position.

**Owner.** This constitution.

**Enforcement.** `composer module-layout-gate` checks R1–R3 in CI.

**Exceptions.** The ratchet in `scripts/module-layout-baseline.json` rejects new and obsolete entries. Existing exceptions must be retired as the layout is corrected.

**Rationale.** Public categories describe supported roles. Internal implementation belongs inside the module.

## API Public surface

### API-001 — Classify each public symbol at its declaration

**Rule.** A public type declares exactly one `Api` or `Spi(Implement|Call)` attribute. The attribute is not inherited.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** `composer php-api` checks source classifications and snapshots. The package graph applies R4/R4b.

**Exceptions.** The product is a consumer and has no PHP API manifest of its own.

**Rationale.** A namespace alone cannot describe reachable signature types or supported use.

### API-002 — Internal types carry no backward-compatibility guarantee

**Rule.** An Internal declaration cannot carry Api or Spi and cannot be imported by another package. `@internal` communicates intent to PHP tools.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** `composer php-api` and `composer package-graph-gate` R2.

**Exceptions.** The finite set of source-bound composition edges in the graph configuration.

**Rationale.** A shared CoreX namespace prevents PHPDoc alone from enforcing package boundaries.

### API-003 — Require a declared symbol for cross-package imports

**Rule.** An import needs a concrete source Api/Spi declaration, a declared dependency and an allowed package direction.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** `composer package-graph-gate` applies R1/R4/R5. A stale generated surface fails R4b.

**Exceptions.** —

**Rationale.** A generated manifest cannot authorize an unknown symbol.

### API-004 — Keep foreign Internal namespaces private

**Rule.** A package cannot import another package’s `Internal` namespace.

**Owner.** This constitution.

**Enforcement.** `composer package-graph-gate` checks R2.

**Exceptions.** —

### API-005 — Declare public maturity

**Rule.** The maturity levels are `stable`, `experimental`, `internal` and `deprecated`. Experimental status lasts a limited number of minor releases. Announce every experimental compatibility change in the package’s `CHANGELOG.md`.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** `composer php-api` checks the public snapshot. Review the package changelog. The automatic Roave BC diff was removed following the owner’s audit on 2026-10-08.

**Exceptions.** —

### API-006 — Preserve compatibility within a supported major version

**Rule.** Within a supported major version only additive changes are allowed. The public API policy lists every prohibited change.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** Review the list of prohibited changes. No single automated check for the complete list is specified.

**Exceptions.** —

### API-007 — Deprecate before removal

**Rule.** A public symbol or external API operation may be removed only in a major release following announced deprecation.

**Owner.** [Public API policy](api-policy.md) and the versioning decision (ADR-008).

**Enforcement.** `scripts/contract-guard.sh` requires a change to the owning package’s `CHANGELOG.md` when its `resources/contracts/php-api.json` changes. Review the deprecation and release version separately.

**Exceptions.** —

### API-008 — Verify the wire and PHP contracts independently

**Rule.** An external API schema snapshot and a backward-compatibility diff of PHP symbols cover distinct contracts. Each requires its own check.

**Owner.** [Public API policy](api-policy.md)

**Enforcement.** `composer api-contract` checks the wire snapshot. `composer php-api` and the golden PHP surface check PHP. The Roave BC diff was removed on 2026-10-08.

**Exceptions.** —

### API-009 — Hide contract implementations

**Rule.** A contract in `packages/<pkg>/src/Contracts/` must be an interface. Keep the implementation private.

**Owner.** [Development doctrine](../dev-doctrine.md) §5.

**Enforcement.** Review and `scripts/contract-guard.sh`.

**Exceptions.** —

## DATA Data ownership

### DATA-001 — Assign a single owner to every table

**Rule.** Register ownership per table. Each table has exactly one owning package.

**Owner.** [Data ownership policy](data-ownership-policy.md)

**Enforcement.** `composer table-ownership-gate` and `composer schema-gate` S9 check owner and scope.

**Exceptions.** —

**Rationale.** The `core`, `attributes`, `workflow` and `wakeup` domains share the `sys_` prefix. A prefix cannot determine table ownership.

### DATA-002 — Limit migrations to owned tables

**Rule.** A package migration may create or alter only tables owned by that package.

**Owner.** [Data ownership policy](data-ownership-policy.md)

**Enforcement.** `composer schema-gate` checks S9 for new tables. Run `composer table-ownership-gate` and review ALTER operations on existing tables.

**Exceptions.** —

### DATA-003 — Route foreign writes through the owner

**Rule.** A package must use the owner’s contract or an event for an effect on another package’s data. Direct SQL and ORM relationship writes into foreign tables are prohibited.

**Owner.** [Data ownership policy](data-ownership-policy.md)

**Enforcement.** `composer table-write-ownership-gate`.

**Exceptions.** —

### DATA-004 — Name cross-package foreign key exceptions

**Rule.** A cross-package foreign key requires a named exception with an owner, reason and removal condition.

**Owner.** [Data ownership policy](data-ownership-policy.md)

**Enforcement.** `composer table-ownership-gate` checks T4.

**Exceptions.** [Cloud role assignment foreign key](exceptions/DATA-004/aut-role-assignments-cloud-fk.md), a permanent named exception.

### DATA-005 — Keep released migrations immutable

**Rule.** From `1.0.0-rc.1`, a released schema-set migration is immutable. Introduce a new shape in a new file and increment `SchemaSet::version` by one in the same commit.

**Owner.** [Schema policy](schema-policy.md)

**Enforcement.** `tests/Golden/SchemaGoldenTest.php` and `SchemaSqliteGoldenTest.php` check the resulting DDL. `composer schema-gate` S8 pins the pre-RC baseline. Release verification separately checks the bytes of released files.

**Exceptions.** —

**Rationale.** Laravel’s `migrations` journal must be able to apply each new shape as a new step.

### DATA-006 — Declare and register new tables

**Rule.** A new table requires explicit approval in the change that introduces it and an ownership entry at introduction.

**Owner.** [Data ownership policy](data-ownership-policy.md)

**Enforcement.** Review the proposed DDL and its ownership entry (G-DDL).

**Exceptions.** —

### DATA-007 — Define a deterministic baseline

**Rule.** Original schema sets start at version 1. The consolidated CoreX 2.0 baseline is generation 2 and the FlexCRM baseline is generation 1. Baseline files are dated `2026_10_01` with one table per file. Use explicit literal object names of at most 63 bytes, bigint counters, UUID keys and an explicit polymorphic pair with string(64). Database enum/set types are forbidden. Keep `up()` pure and preserve supported drivers. The schema policy lists the finite exceptions for smaller integer types.

**Owner.** [Schema policy](schema-policy.md)

**Enforcement.** `composer schema-gate` checks S1–S9. Golden schemas verify the final shape and drivers. Review the layout and type rules outside the static gate.

**Exceptions.** `scripts/schema-gate-allowlist.json` records table.column and reason for S6.

**Rationale.** Deterministic schema and naming allow baseline verification independently of consumer configuration.

## RUN Runtime

### RUN-001 — Scope the current tenant

**Rule.** Pass context explicitly or resolve it at the request or job boundary. A global mutable current tenant is prohibited.

**Owner.** This constitution.

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### RUN-002 — Keep durable data serializable

**Rule.** Only scalars, `null`, backed enums, `DateTimeImmutable` and arrays of these may cross a durable boundary, including queues, outboxes, checkpoints, wakeups and bulk work.

**Owner.** This constitution.

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### RUN-003 — Keep runtime objects inside their process

**Rule.** A `Closure`, Eloquent model, request, container or context object cannot cross a durable boundary.

**Owner.** This constitution.

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### RUN-004 — Inject the time source

**Rule.** Obtain the current time from an injected clock. Global time helpers are prohibited outside the Laravel bridge.

**Owner.** This constitution.

**Enforcement.** `composer runtime-globals-gate` checks G2 for global helpers including `now()` and G3 for clock access such as `Carbon::now()`, argumentless `new DateTime()` and `time()`. The historical exceptions under `packages/tenancy/src/Impersonation/` have been retired. `scripts/runtime-globals-baseline.json` is empty.

**Exceptions.** —

### RUN-005 — Isolate requests and jobs in resident workers

**Rule.** Mutable state must not survive request or job boundaries. Octane must preserve the behaviour of isolated requests.

**Owner.** This constitution.

**Enforcement.** `composer runtime-globals-gate` G4 checks static properties and `static $x`. `composer test:arch` runs `tests/Arch/SingletonInjectionTest.php` L1–L3 with an empty allowlist. A singleton cannot capture `Application`, `Request`, the configuration repository, a scoped type or a connection. `tests/Feature/ResidentLifecycleTest.php` verifies that `forgetScopedInstances()` clears scoped instances and tenant context. `packages/tenancy/tests/Pg/ResidentTenantSwitchPgTest.php` (group `pg`) checks that a write after a tenant switch reaches the current tenant’s physical database.

**Exceptions.** —

### RUN-006 — Align scoped instances with the tenant lifetime

**Rule.** Clear scoped instances on every context change, including `initialize`, `end` and exit from `runFor`. A scoped or transient service resolved inside the context captures its connection. Do not retain a resolved scoped service across a context change, even in a local variable that survives `runFor`. A service taking `Connection|ConnectionInterface` cannot be a singleton.

**Owner.** This constitution.

**Enforcement.** `packages/tenancy/tests/Pg/ScopeBoundaryPgTest.php` (group `pg`) proves that services resolved outside and inside `runFor` write to different databases. Removing `forgetScopedInstances()` from `resetScope()` makes this test fail. `tests/Arch/SingletonInjectionTest.php` L1–L3 forbids singleton capture of a connection.

**Exceptions.** —

**Rationale.** Services receive connections in their constructors. Tenant transitions must give each new service the correct physical connection.

## CFG Configuration

### CFG-001 — Give each configuration key one class

**Rule.** Classify every configuration key as exactly one of `deployment`, `product-policy`, `runtime-settings` or `secret`.

**Owner.** [Configuration policy](config-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### CFG-002 — Read configuration at the composition boundary

**Rule.** Only a composition boundary such as a service provider or console command may call `config()`. Pass values to services through their constructors.

**Owner.** [Configuration policy](config-policy.md)

**Enforcement.** `composer config-composition-gate` rejects C1 (`config()`), C2 (configuration repository access), C3 (static `Config`) and C4 (`env()`) outside the composition paths in `scripts/composition-boundary.json`.

**Exceptions.** —

**Rationale.** The gate blocks reintroduction of direct configuration reads in services.

### CFG-003 — Use typed, immutable options

**Rule.** Pass configuration to services in an immutable typed options object. Arrays and string keys cannot substitute for the options type.

**Owner.** [Configuration policy](config-policy.md)

**Enforcement.** `composer test:arch` runs `tests/Arch/OptionsSingletonTest.php` to ensure Options are lazy singletons and never eagerly bound. `composer configuration-reference` requires each registry row’s `typed_options_field` to name an options field.

**Exceptions.** —

### CFG-004 — Validate during application boot

**Rule.** Detect invalid configuration while the application loads, before the first use.

**Owner.** [Configuration policy](config-policy.md)

**Enforcement.** Validate the Options shape in `boot()`. Run `RuntimeConfigGate` via `php artisan packman:config:validate --production`. `composer config-cache-check` runs `tests/Feature/ConfigCacheTest.php`, checks `var_export` → `eval` for every `packages/*/config/*.php` and boots the app from its cache.

**Exceptions.** —

### CFG-005 — Protect secret values

**Rule.** Do not read secrets as ordinary configuration or include them in dumps, logs or error messages.

**Owner.** [Configuration policy](config-policy.md)

**Enforcement.** Secrets are excluded from Options. `RuntimeConfigGate` checks presence and `composer configuration-reference` records the secret default as `***`.

**Exceptions.** —

### CFG-006 — Use CoreX public configuration names

**Rule.** Public configuration identifiers in `corex/*` must use CoreX names, including configuration keys, environment variables, Artisan commands and publish tags.

**Owner.** [Development doctrine](../dev-doctrine.md) §3.

**Enforcement.** Review.

**Exceptions.** —

**Rationale.** `corex-search-intl` matches the package name `axiomasoft/corex-search-intl`. Announce configuration renames in the package changelog.

## MSG Messaging

### MSG-001 — Classify each message

**Rule.** Each message belongs to exactly one class: domain event, integration event or command.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-002 — Declare integration events

**Rule.** A framework event becomes a published integration contract only through explicit declaration.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-003 — Use the published envelope format

**Rule.** Use CloudEvents 1.0 structured JSON. The messaging policy specifies the mapping and profiles.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** `packages/core/tests/Unit/CloudEventCodecTest.php`.

**Exceptions.** —

**Rationale.** The wire format is standardized and the internal and external profiles have fixed fields.

### MSG-004 — Version published event names

**Rule.** A published event must have a versioned name independent of its PHP class name.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-005 — Exclude Eloquent models from payloads

**Rule.** A message payload cannot contain a serialized Eloquent model.

**Owner.** This constitution, as a specific case of RUN-003.

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-006 — Make at-least-once consumption idempotent

**Rule.** Consumers must tolerate repeated delivery of the same message.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-007 — Enforce classification and size limits

**Rule.** A message must carry its personal-data classification and stay within its declared size limit.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### MSG-008 — Publish only committed facts

**Rule.** Publish an integration event only after the change it describes commits.

**Owner.** [Messaging policy](message-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

## EXT Extensions

### EXT-001 — Change the closed extension set only by decision

**Rule.** Introducing a new extension mechanism or a ninth extender requires an RFC/ADR.

**Owner.** [Development doctrine](../dev-doctrine.md) §4 (ADR-011 and ADR-014).

**Enforcement.** Packman’s `Packman\Modules\Contracts\Compiler\Extender` documents the closed set. Its registry compiler rejects unknown extenders. For outgoing webhooks, `vendor/bin/pest --exclude-group=pg packages/webhooks/tests/Feature/WebhookEgressPolicyTest.php --filter=revocation` and its guard-removal control verify the egress policy. [Webhook threat model](webhooks-threat-model.md)

**Exceptions.** —

### EXT-002 — Type registries by contribution

**Rule.** Maintain a registry per contribution kind. A universal registry for unrelated contributions is prohibited.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** Review.

**Exceptions.** —

### EXT-003 — Specify the registry contract

**Rule.** Every registry must declare the identifier type, duplicate policy, freeze point, ordering, cache invalidation and relationship to the tenant.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### EXT-004 — Freeze registrations

**Rule.** A registry progresses through `collecting → compiled → frozen`. Registration after freezing is forbidden.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### EXT-005 — Define deterministic contribution order

**Rule.** Declare a deterministic ordering. Equal priorities cannot resolve conflicts.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### EXT-006 — Reject duplicate extension IDs

**Rule.** A duplicate identifier at an extension point must cause a compilation error. Silent overrides are forbidden.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** `corex-scheduler` combines `corex-scheduler.handlers` with `WakeupHandlerDefinition::TAG` (`corex.scheduling.wakeup-handlers`). A duplicate `kind` raises `InvalidConfiguration` at boot. Due-time sources use `DueWakeupSource::TAG` (`corex.scheduling.due-sources`).

**Exceptions.** —

### EXT-007 — Record extension identity in durable decisions

**Rule.** A durable decision that depends on installed extensions must store the registry fingerprint or the identifier and version of the extension used.

**Owner.** [Extension policy](extension-policy.md)

**Enforcement.** Review. No automated check is listed.

**Exceptions.** —

### EXT-008 — Keep changes within the module owner

**Rule.** Monkey-patching another module is forbidden.

**Owner.** [Development doctrine](../dev-doctrine.md) §4.

**Enforcement.** Review.

**Exceptions.** —

### EXT-009 — Treat module renames as data migrations

**Rule.** The module ID equals the package’s Composer name and is stored in `mod_*`. Changing it requires a data migration. Editing the manifest alone is insufficient.

**Owner.** [Development doctrine](../dev-doctrine.md) §3.

**Enforcement.** Review.

**Exceptions.** —

## NON Non-goals

The following work is excluded. Reconsidering a non-goal requires the stated condition or a new recorded architecture decision that supersedes the existing policy.

| ID | Excluded work | Condition or governing decision |
|:--|:--|:--|
| NON-001 | Move directories or rename symbols before the underlying semantics are fixed | Settle the semantics first. |
| NON-002 | Create physical `packages/capabilities/`, `packages/integrations/` or `packages/kernel/` directories | Review if the package count grows radically. |
| NON-003 | Extract `Money`, `Features`, `Departments`, `Settings`, `Audit`, `PubSub`, `Storage` or `Views` into separate Composer packages | A separate release lifecycle or an external consumer independent of the rest of the kernel. |
| NON-004 | Rename `Extend` to `Extension(s)` or `Gate` to `Activation` in `axiomasoft/corex` | Names are governed by ADR-011, ADR-014 and the development doctrine. |
| NON-005 | Use date-based external API versions (`YYYY-MM`) as the primary compatibility mechanism | Supersede the API versioning decision. |
| NON-006 | Release external API versions on a quarterly cadence | Supersede the API versioning decision. |
| NON-007 | Make GraphQL canonical or add its schema, adapter and dependency | Requires a new transport decision. |
| NON-008 | Split out transport packages (`operations-http`, `operations-tools`) | Demonstrate dependency pressure. |
| NON-009 | Maintain a handwritten public symbol list (`api/public.php`) | Supersede the source-based public API policy. |
| NON-010 | Add a second boundary tool alongside the package graph gate | Identify a rule that the existing gate cannot express. |
| NON-011 | Add runtime Composer dependencies to `corex/*` | An explicit decision must introduce the dependency. Development dependencies also require a named decision. |
| NON-012 | Add `SubjectType`/`SubjectId`/`SubjectRef` on top of the existing opaque record identity | The opaque identity already fulfils this requirement. |
| NON-013 | Enable SaaS request-cost limits by default on self-hosted installations | Change the cost-enforcement policy explicitly. |
| NON-014 | Generate the domain model from the external API schema | Requires a new domain-ownership decision. |
| NON-015 | Load Eloquent models in transport-layer resolvers | Requires a new transport-boundary decision. |
| NON-016 | Pass context through a mutable global current tenant | RUN-001. |
| NON-017 | Bypass the entity registry through direct access to the model class | EXT-008. |
| NON-018 | Store event subscriptions in the database | Development doctrine §4, informed by Bitrix. |
| NON-019 | Use string-based hooks with numerical priorities | Development doctrine §4, informed by WordPress. |
| NON-020 | Override components by alias in place of a stable handle | Development doctrine §4, informed by October. |
| NON-021 | Expand the closed extender set without an RFC | EXT-001, informed by Flarum. |
| NON-022 | Construct database tables at runtime using an information-block model | Development doctrine §4, informed by Bitrix. |

Runtime code cannot reference its own or another package’s development bridges in `Tooling/`, `PHPStan/` or `Testing/`. The `module-layout` gate checks namespace references in R6. Shared runtime implementation belongs in `Internal/`.
