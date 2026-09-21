# P11 — нормативный дизайн compile/freeze registries

## 0. Статус, границы и словарь

Этот документ — исполнимый контракт P11. Он не вводит God Registry,
не расширяет закрытый набор из восьми extenders, не переименовывает
`Extend`/`Gate` и не меняет миграции. Все release-visible изменения идут в
единый корневой `CHANGELOG.md`.

Термины:

- **declaration** — module-authored typed contribution в `Manifest`;
- **normal form** — internal compiler IR с identity, owner/version, ordering и replacement;
- **compiled snapshot** — канонические bytes в `bootstrap/cache/corex_modules.php`;
- **frozen** — authority snapshot не изменяется из runtime consumer; новая версия
  появляется только как целый опубликованный snapshot;
- **projection** — typed read model над snapshot и current tenant/policy state;
- **activation epoch** — tenant-local версия enable/disable state; не registry fingerprint;
- **extension stamp** — point + id + version и diagnostic registry fingerprint,
  сохранённые в durable decision.

`RAG:—` Текущий `CompiledRegistry` — `readonly` лишь на уровне своих
свойств: в массивах лежат mutable extender objects с public properties.
`ArrayEntityRegistry::register()` после boot не закрыт. `ReadRegistryIntrospection`
считает отдельный hash вместо потребления authority fingerprint.

## 1. Карта registry-like поверхностей

| Surface | Класс | Authority | P11 outcome |
|:--|:--|:--|:--|
| `RegistryCompiler` / `CompiledRegistry` | code registry | module manifests + installed package metadata | Канонический compile, duplicate/replacement, fingerprint, frozen publication. |
| `ArrayEntityRegistry` | runtime projection | compiled entity slice + boot contributions | Collect до application `booted`, затем `freeze()`; duplicate — error. |
| `OperationRegistry` / `CompiledToolCatalogue` | projection | compiled API slice | Не получают второй lifecycle; их revision включает upstream fingerprint + projection policy. |
| `ModulePanelCatalogue` | boot-built typed catalogue | tagged providers | Constructor validates unique key and freezes map; tenant/module checks remain live. |
| P10 `CompositeFieldCatalogue` | data catalogue | static/module/database sources | Own `schemaHash`/source fingerprint from P10; P11 does not replace it with module fingerprint. |
| `SearchManager` | driver factory | Laravel container/config | **Not a registry**: no contribution catalogue, identity set or compile artifact. D5 does not force a fake freeze layer here. |
| wakeup handler map | durable handler registry | typed handler definitions at boot | Explicit kind/version, frozen map, durable stamp and mismatch quarantine. |
| P5 message registry | immutable contract registry | package-owned declarations | Already follows build-time duplicate rejection; P11 only verifies consistency, does not duplicate it. |

The normal-form table is compiler-private. It is not queryable at runtime and therefore does not
violate EXT-002. Runtime consumers continue to receive typed slices.

## 2. Stable identity for every compiled slice

Identity is point-specific. Owner is included only where the point deliberately admits several
contributors to the same semantic subject.

| Point | Canonical id | Collision rule |
|:--|:--|:--|
| `entity` | resolved entity handle | Global duplicate is compile error. |
| `entity_field` | `entityHandle.fieldHandle` | Global duplicate is compile error. |
| `permissions` | `panel:entity` | One effective group per slot; action arrays are payload, not identity. |
| `api.operation` | `version:operationId` | Duplicate id or normalized method/path is compile error. |
| `api.tool` | `module:toolCode:toolVersion` | Existing tuple remains canonical; wire-name collision is also error. |
| `api.resource` | `module:mode:entityHandle` | Multi-contributor, therefore owner is part of identity. |
| `filament` | `module:panel:kind:class-or-entity` | Multi-contributor; class lists are expanded into records before hashing. |
| `menu` | `panel:handle` | Global duplicate; `afterHandle` controls order, not identity. |
| `routes` | `module:kind:normalized-file:prefix` | Multi-contributor; duplicate within owner is error. Host absolute path never enters id. |
| `workflow.step` | step class-string | Global duplicate. |
| `workflow.trigger` | `module:entityHandle:eventName` | One normalized record per event. |
| `settings` | `namespace:key` | Global duplicate. |
| `feature_flags` | flag key | Global duplicate. |
| `listeners` | `module:event:listener` | Exact duplicate is error; order is lexical. |

The compiler expands list-valued declarations before duplicate checking. Error text always names
point, canonical id, first owner and second owner.

## 3. Compiler-private normal form and explicit replacement

`packages/modules/src/Compiler/NormalizedExtension.php` is internal IR:

```php
final readonly class NormalizedExtension
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $point,
        public string $id,
        public string $owner,
        public string $ownerVersion,
        public array $payload,
        public ?string $after = null,
    ) {}

    public function key(): string
    {
        return $this->point."\0".$this->id;
    }
}
```

Replacement metadata belongs to the manifest rather than being copied into eight extender types:

```php
final readonly class ExtensionReplacement
{
    public function __construct(
        public string $point,
        public string $targetId,
        public string $replacementId,
    ) {}
}

// Manifest: additive API, repeatable.
public function replace(string $point, string $targetId, string $replacementId): self;
```

Rules:

1. target and replacement exist in the same point after normalization;
2. replacement is owned by the manifest that declares the directive;
3. one target has at most one incoming replacement;
4. replacement graph is acyclic;
5. target remains in provenance but is removed from the effective typed slice;
6. replacement never grants ownership of the target's tables, routes or data;
7. missing target, foreign replacement, duplicate incoming edge and cycle are distinct compile
   errors.

No implicit last-wins path remains.

## 4. Deterministic order

Before any stage, manifests are sorted by `composerName ASC`. Normalized maps are keyed, never
dependent on filesystem, installed.json, provider or array insertion order.

- default order: canonical id ASC;
- a future point that explicitly declares numeric priority: `priority DESC, id ASC`;
- `menu`: stable topological sort by `afterHandle`; the zero-incoming frontier is id ASC;
- missing `afterHandle` target or cycle is a compile error;
- API dispatch may apply its more specific literal-route ordering after reading the already stable
  compiled slice; that projection order does not change registry identity;
- input arrays whose order carries no declared meaning are recursively key-sorted; semantic lists
  are preserved only when the point contract says order matters.

This supersedes D5's unconditional wording `priority DESC, id ASC`: current extenders do not expose
a common priority and P11 does not add a meaningless one merely to satisfy the sentence.

## 5. Module version metadata and canonical fingerprint

`InstalledPackages` gains a metadata read without removing its current name-only method:

```php
final readonly class InstalledPackageVersion
{
    public function __construct(
        public string $name,
        public string $version,
        public ?string $reference,
    ) {}

    /** @return array{name: string, version: string, reference: ?string} */
    public function canonical(): array;
}

/** @return array<string, InstalledPackageVersion> keyed by package name */
public static function discoverVersions(?string $installedJsonPath = null): array;
```

No install path, timestamp, hostname, absolute file name, tenant id or activation state enters the
fingerprint. `reference` is Composer source/dist reference when present; it distinguishes two dev
builds with the same pretty version.

Canonical payload:

```json
{
  "schema": "corex-registry/1",
  "compiler": {"package": "corex/modules", "version": "...", "reference": "..."},
  "modules": [{"name": "...", "version": "...", "reference": "..."}],
  "extensions": [{"point": "...", "id": "...", "owner": "...", "ownerVersion": "...", "payload": {}}],
  "replacements": [{"point": "...", "target": "...", "replacement": "...", "owner": "..."}]
}
```

Objects are recursively normalized, encoded with `JSON_THROW_ON_ERROR |
JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, then fingerprinted as
`sha256("corex-registry/1\0" . bytes)`.

`CompiledRegistry` carries `schemaVersion` and `fingerprint`; `toArray()` persists both. Loading a
missing/unknown schema or a hash mismatch fails with a typed stale/corrupt artifact exception and
in production tells the operator to rerun `corex:modules:compile`. `ReadRegistryIntrospection`
returns this authority fingerprint instead of calculating a second revision.

## 6. Frozen snapshot and runtime mutation boundary

The authority retained by `CompiledModuleRegistry` is canonical scalar data, not shared mutable
extender instances. `compiled()` hydrates a detached typed view from those bytes. Mutation of a
consumer's returned object cannot alter the next `compiled()` result or its fingerprint.

`ArrayEntityRegistry` is the only imperative collector in the current runtime path:

```php
private bool $frozen = false;

public function register(EntityDefinition $definition): void
{
    if ($this->frozen) {
        throw RegistryFrozen::for('entity', $definition->handle);
    }

    if (isset($this->entities[$definition->handle])) {
        throw DuplicateExtensionId::for('entity', $definition->handle, ...);
    }

    $this->entities[$definition->handle] = $definition;
}

public function freeze(): void
{
    $this->frozen = true;
}
```

`ModulesServiceProvider` projects compiled entities, builds morph map/audit wiring, then freezes the
collector in its existing `booted` callback. `EntityRegistry` remains read-only; no contract method
is added. Tests prove late registration throws and does not alter morph/registry state.

## 7. Publication, worker refresh and tenant activation

Publication remains the existing verified tmp+rename of one complete PHP artifact. There is no
in-place edit and no per-slice swap.

- FPM/ordinary boot: next process/request loads the new artifact.
- `queue:work`: the existing `JobProcessing` listener clears the memo before a job. The first read
  pins one snapshot for that job; a mid-job file replacement is not observed.
- Octane: no optional Octane runtime dependency is added. Deploy must run `octane:reload`; one
  request uses the snapshot already loaded by its worker.
- Fleet: P11 does **not** promise a single global switch instant. Mixed old/new workers can exist
  during graceful replacement, therefore durable work carries its own extension stamp.

The compiled fingerprint is deployment/code state. Tenant enable/disable/upgrade remains a separate
activation epoch in `mod_lifecycle_log`. It changes `active()`/`isActive()` cache keys but never
rewrites or re-fingerprints the code snapshot. No account-specific compiled file is introduced.

Module lifecycle is the extension lifecycle:

`declare/register -> validate/compile -> publish/freeze -> tenant enable/execute/disable ->
upgrade/archive/purge`.

There is no second per-extension install FSM. Deactivation blocks new tenant dispatch but does not
mutate the frozen code catalogue; persisted morph identity and durable provenance remain readable.

## 8. Durable extension stamp

`corex/modules` supplies one dependency-neutral data object; all consumers already depend on the
package:

```php
final readonly class ExtensionStamp
{
    public function __construct(
        public string $point,
        public string $id,
        public string $version,
        public string $registryFingerprint,
    ) {}

    /** @return array{point: string, id: string, version: string, registryFingerprint: string} */
    public function toArray(): array;

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): self;
}
```

The execution rule is narrow:

1. if a durable decision names a specific extension id+version, exact id+version controls replay;
2. global fingerprint is provenance and detects missing/unknown catalogue, but unrelated registry
   changes do not invalidate an otherwise exact extension stamp;
3. fingerprint alone is allowed only when the decision depends on the whole extension set;
4. mismatch never silently invokes the current implementation; it yields a stable failure/quarantine
   reason and requires explicit reschedule/migration;
5. tenant activation and authorization are rechecked at execution and are not encoded as authority
   in the stamp.

No new SQL column is required: existing JSON payloads or `version_ref` carry the additive stamp.

## 9. Workflow durable intent (P11.4)

`TriggerRequest` already requires `definitionRef` and `versionRef`; P11 validates both as non-empty
stable identifiers. `SqlWorkflowBinding` derives:

```php
$stamp = new ExtensionStamp(
    point: 'workflow.trigger',
    id: $request->module.':'.$request->entityHandle.':'.$request->eventName,
    version: $request->versionRef,
    registryFingerprint: $this->modules?->compiled()->fingerprint ?? 'module-free',
);
```

The stamp is stored as provenance beside the existing JSON payload. Replay identity and
`payload_hash` include exact point/id/version plus business payload, but exclude
`registryFingerprint`: unrelated registry additions must not conflict with durable work.
Existing rows with non-empty `definition_ref` + `version_ref` use the explicit legacy
no-provenance normal form and remain replayable; they are never silently rewritten to claim a
fingerprint. An empty version is rejected before insert.

The external `EngineAdapter` remains independently owned. P11 does not add a generic engine, queue
or schema migration.

## 10. Wakeup handler registry and replay (P11.5)

Raw `kind => class-string` config becomes a typed, frozen definition map while retaining an explicit
legacy parser for installation transition:

```php
final readonly class WakeupHandlerDefinition
{
    /** @param class-string<WakeupHandler> $handler */
    public function __construct(
        public string $kind,
        public string $version,
        public string $handler,
        public bool $acceptLegacyUnstamped = false,
    ) {}
}
```

Canonical config row:

```php
'notification.digest.v1' => [
    'handler' => DigestWakeupAdapter::class,
    'version' => 'notification-digest/1',
    'accept_legacy_unstamped' => false,
],
```

Provider compilation rejects duplicate kind, empty version, wrong class and conflicting definitions.
`SqlWakeupScheduler` stores an envelope in the existing `payload` JSON:

```json
{
  "_corex": {
    "schema": "wakeup-payload/2",
    "extension": {"point": "wakeup.handler", "id": "...", "version": "...", "registryFingerprint": "..."}
  },
  "data": {}
}
```

`WakeupRequest::$payload` exposed to the handler remains the unwrapped `data`, preserving the public
contract. Digest covers the envelope. Before invocation `SweepRunner` compares persisted id/version
to the current definition. Missing stamp is admitted only by that exact definition's explicit
`acceptLegacyUnstamped`; otherwise the row becomes terminal `handler-stamp-missing`. Version mismatch
becomes terminal `handler-version-mismatch`. Neither calls the handler and neither is retried.

A read-only `corex:wakeup:validate-stamps` command reports pending/running legacy rows before deploy;
it does not rewrite them. Operators drain, cancel or explicitly configure the bounded legacy bridge.

## 11. tasks-workflow and notifications consumers (P11.6–P11.7)

### 11.1 tasks-workflow

Current code schedules only `seriesId` and `immutableRevision`, while
`MaterializationWakeupHandler` requires `definitionHash`; the handler therefore returns without
materializing. P11.6 fixes the producer and binds all hashes:

```php
payload: [
    'seriesId' => $series->id,
    'immutableRevision' => $series->immutableRevision,
    'definitionHash' => $series->definitionHash,
    'start' => $window->start,
    'end' => $window->end,
]
```

`MaterializationHasher::payload()` and wakeup `WriteToken` include definition hash, window and the
handler extension version. Empty/mismatched data throws a typed stale-materialization error; it is
not a silent return. The consumer contributes handler version `task-materialization/1`.

Completion capture keeps `definitionRef=task-completion/1`, changes `versionRef` from ambiguous
`1` to `task-completion/1`, and proves replay conflict when that version changes.

### 11.2 notifications

The digest consumer registers a full definition for `notification.digest.v1` with version
`notification-digest/1`. Existing kind already carries v1, but legacy unstamped acceptance is an
explicit bounded setting, not inferred from the suffix. Tests cover exact-version replay and
version mismatch without invoking `DigestWakeupAdapter`.

No consumer writes `corex-wakeup.handlers` after wakeup's registry has been resolved. Provider order
is tested; late contribution fails instead of becoming request-order dependent.

## 12. Reusable extension contract suite (P11.8)

`CoreX\Modules\Testing\ExtensionContract` has no Pest/PHPUnit dependency. External extension tests
pass manifests and installed package metadata and receive a typed report:

```php
$report = ExtensionContract::verify(
    manifests: [$module->manifest()],
    installedPackages: ['corex/core' => new InstalledPackageVersion(...)],
);

if (! $report->passed()) {
    throw new RuntimeException($report->summary());
}
```

The suite proves:

1. compile succeeds from both original and reversed input order;
2. canonical bytes and fingerprint are identical;
3. round-trip preserves bytes/fingerprint;
4. every identity is unique and every replacement resolves without cycle;
5. late entity registration fails after freeze;
6. atomic write/load rejects truncated, wrong-schema and wrong-hash artifacts;
7. durable fixture executes only under exact id+version;
8. diagnostics name both owners for a duplicate.

Root `scripts/extension-contract-gate.php` runs positive and negative fixtures. P17 adds truly
external packages/consumers; P11 owns the reusable suite and one model fixture only.

## 13. Validation recipe and acceptance

1. Module pure lane: compiler permutations, all point identities, replacement graph, canonical
   JSON, fingerprint sensitivity and ignored ambient fields.
2. Module PG lane: lifecycle enable/disable/upgrade changes activation epoch but not code
   fingerprint; cache visibility remains cross-process-safe.
3. Worker lane: same job/request view is stable; next queue job after flush sees new fingerprint;
   no claim of fleet-wide atomicity.
4. Workflow: exact definition/version persisted; hash includes stamp; legacy id+version remains
   readable; empty version rejected.
5. Wakeup: stamped schedule/replay, mismatch/missing quarantine, no handler call, legacy preflight.
6. tasks-workflow: scheduled payload includes definition hash/window; handler no longer silently
   no-ops; replay hash is stable.
7. notifications: versioned digest handler definition and mismatch test.
8. Projection checks: OperationRegistry, ToolCatalogue, PanelCatalogue and P10 FieldCatalogue do not
   create independent mutable authority; SearchManager remains a driver factory.
9. Run affected package lint/analyse/test, PostgreSQL lanes after isolation preflight, root
   `composer lint:check && composer analyse && composer test && composer test:pg`, architecture
   gate, mutation gate and public API snapshot.
10. Root changelog and `findings/P11-receipts.md` name fingerprint schema, lifecycle boundaries,
    legacy-row verdict and all exact validation carriers.

Acceptance is green only when a changed extension order produces identical bytes, a changed
extension/module version changes the expected stamp, and no durable replay invokes a different
implementation silently.

## 14. Decision map

D56 classifies authorities/projections/factories. D57 replaces D5 with the full canonical compile
contract. D58 separates publication from tenant activation and defines worker boundaries. D59
defines durable stamp and mismatch behavior. External evidence is `P11-design-rag.md`.
