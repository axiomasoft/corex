# Extension policy

Articles `EXT-001` through `EXT-009` of the constitution define the invariants. Section 4 of the [development doctrine](../dev-doctrine.md) lists the permitted extension mechanisms.

## Registry declarations

| Property | Requirement |
|:--|:--|
| Identifier | Name its type and derivation rule. The identifier must be stable. |
| Duplicates | Reject duplicates during compilation. Explicit replacement requires an existing target and an acyclic replacement graph. |
| Freeze point | State exactly when registration closes. |
| Order | Declare a deterministic order with a secondary rule for equal priorities. |
| Invalidation | Declare the inputs to the artifact fingerprint and the changes that trigger a rebuild. |
| Tenant scope | Declare whether the artifact is shared or account-specific. |

## Identity at each compiled extension point

Each extension point owns its identity rule. Include the owner in the identifier only when that point permits several contributors for the same subject. Reject collisions before projection and identify the point, canonical ID and both owners in the diagnostic. An explicit `ModuleManifest::replace(point, targetId, replacementId)` can replace a contribution within the same point. Paths, timestamps, tenants and activation epochs are excluded from identity and version metadata.

| Point | Canonical ID | Collision rule |
|:--|:--|:--|
| `entity` | Resolved entity handle | Reject global duplicates. |
| `entity_field` | `entityHandle.fieldHandle` | Reject global duplicates. |
| `permissions` | `panel:entity` | One effective group per slot. The action array is payload. |
| `api.operation` | `version:operationId` | Reject duplicate IDs and duplicate normalized method/path pairs. |
| `api.tool` | `module:toolCode:toolVersion` | Keep the existing tuple canonical. Reject wire-name collisions as well. |
| `api.resource` | `module:mode:entityHandle` | Include the owner to allow multiple contributors. |
| `filament` | `module:panel:kind:class-or-entity` | Allow multiple contributors. Expand class lists into records before hashing. |
| `menu` | `panel:handle` | Reject global duplicates. `afterHandle` controls order. |
| `routes` | `module:kind:normalized-file:prefix` | Allow multiple contributors and reject duplicates within one owner. Exclude the host's absolute path from the ID. |
| `workflow.step` | Step class-string | Reject global duplicates. |
| `workflow.trigger` | `module:entityHandle:eventName` | Keep one normalized entry per event. |
| `settings` | `namespace:key` | Reject global duplicates. |
| `feature_flags` | Flag key | Reject global duplicates. |
| `listeners` | `module:event:listener` | Reject exact duplicates and order entries lexically. |

A durable decision that references a specific extension carries an `ExtensionStamp` with its point, ID, version and diagnostic registry fingerprint. The exact ID and version identify the extension.

## Registry lifecycle

1. In `collecting`, gather contributions.
2. In `compiled`, publish an immutable artifact and its fingerprint.
3. In `frozen`, reject further registration as an error.

The fingerprint covers the enabled module set and versions, normalized declarations, the `corex-registry/1` schema version and the build version. Exclude host paths, timestamps, tenant IDs, activation epochs and discovery order from the canonical bytes. A change to a fingerprinted input invalidates the compiled artifact.

## Authorities and projections

| Surface | Classification | Responsibility |
|:--|:--|:--|
| `RegistryCompiler` → `CompiledRegistry` → `CompiledModuleRegistry` | Authoritative code snapshot | Produce one canonical compilation, fingerprint and published artifact. |
| `ArrayEntityRegistry`, `OperationRegistry`, `CompiledToolCatalogue`, `ModuleFieldCatalogue` | Projection | Read frozen declarations. Registration of new code extensions is closed. |
| `ModulePanelCatalogue` and the wakeup handler map | Catalogue built at boot | Collect typed contributions once and reject duplicates and late mutations. |
| `CompositeFieldCatalogue` and other field catalogues | Data catalogue | Maintain a separate data-schema `schemaHash` alongside the module fingerprint. |
| `SearchManager` | Driver factory | Select a driver. No compilation or freeze phase is required. |

`scripts/extension-contract-gate.php` classifies every `Registry`, `Catalogue` and `Manager` class or interface by name, including runtime managers that do not implement catalogues. Introducing a candidate requires an explicit classification. `Packman\Modules\Testing\ExtensionContract::verify()` takes manifests and explicit `InstalledPackageVersion` values from scalar fixtures. It returns a typed result with `passed()`, `summary()` and a fingerprint. Pest, PHPUnit and external package suites can run this check without a Laravel container.

## Replaying a durable decision

When delayed execution depends on the set of extensions, store the registry fingerprint or the chosen extension's ID and version with the decision. This allows workers to detect intervening activation or upgrade when retrying the job.

For work that names a specific extension, replay must match the exact `point + id + version`. Keep `registryFingerprint` for diagnostic provenance and exclude it from the request hash and wakeup replay digest (D59, D103). Refuse execution when the version differs or a required stamp is missing. Legacy unstamped wakeups require explicit opt-in for each handler. Workflow retains its legacy identity algorithm for previously stored references.

## Replacing snapshots in long-lived processes

Build and verify the replacement before publishing it atomically with its new version. Workers adopt it at a job boundary. A running job retains the snapshot it started with.

Tenant activation has its own epoch and leaves the code fingerprint unchanged. Queue workers clear memoized state before the next job. Octane picks up a new code snapshot on deployment reload. Workers across a fleet can transition at different times.
