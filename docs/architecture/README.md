# CoreX architecture

The constitution defines the level 0 invariants. The policies describe how to apply them. Start with the applicable article and follow its enforcement field to the check or review procedure.

## Directory

| Document | Use |
|:--|:--|
| [Constitution](constitution.md) | Find a normative article, its owner, enforcement and permitted exceptions by ID. |
| [Package taxonomy](taxonomy.md) | Change `extra.corex`, `package-type` or `stability`. |
| [Naming vocabulary](naming.md) | Choose class suffixes and names for public parameters and fields. |
| [Rename register](rename-registry.md) | Check former names, replacements and migration conditions. |
| [Module layout](structure-profiles.md) | Apply STR-002–STR-006 to package roots and feature modules. |
| [API policy](api-policy.md) and [PHP compatibility](semver-policy.md) | Review supported calls, implementations, deprecations and breaking changes. |
| [Configuration policy](config-policy.md) | Classify configuration and introduce keys under CFG articles. |
| [Schema policy](schema-policy.md) | Define schema sets, scope, execution and layout under DATA-005 and DATA-007. |
| [Data ownership](data-ownership-policy.md) | Assign table owners and evaluate cross-package foreign keys. |
| [Message policy](message-policy.md) | Define envelopes, event names and message versions. |
| [Extension policy](extension-policy.md) | Define registries, contribution identity and extension lifecycles. |
| [Ecosystem dependencies](ecosystem-dependencies.md) | Install pinned external dependencies and prepare CI. |
| [External design references](references.md) | Trace the mechanism behind a design choice. |
| [Rejected optimizations](../rejected-optimizations.md) | Identify previous decisions and the conditions for reconsidering them. |

Named exceptions include an owner and a removal condition. See the [exception register](exceptions/). The import/export package is active under `packages/import-export` as `axiomasoft/corex-import-export`. The former empty-directory rule, PKG-009, is retired.

## Enforcement index

The initial review counted automated checks for 13 of its 58 articles. The index below points to the current constitution. Each row names the primary evidence or the human review required for that article. The [module layout policy](structure-profiles.md) defines the structural checks. Existing layout exceptions are recorded in [the layout baseline](../../scripts/module-layout-baseline.json).

| Article | Requirement | Check or review |
|:--|:--|:--|
| [PKG-001](constitution.md#pkg-001--state-the-package-purpose) | State the package purpose | Review of the package purpose |
| [PKG-002](constitution.md#pkg-002--dependencies-point-down-the-layers) | Dependencies point down the layers | `composer test:arch` (`LayerTest`) |
| [PKG-003](constitution.md#pkg-003--the-kernel-is-independent) | The kernel is independent | `composer test:arch` |
| [PKG-004](constitution.md#pkg-004--declare-cross-package-dependencies) | Declare cross-package dependencies | `composer package-graph-gate` R1/R6 |
| [PKG-005](constitution.md#pkg-005--guard-optional-integrations) | Guard optional integrations | Package graph guard validation |
| [PKG-006](constitution.md#pkg-006--keep-domain-contracts-with-the-host) | Keep domain contracts with the host | Package graph R2 and contract review |
| [PKG-007](constitution.md#pkg-007--keep-product-literals-out-of-foundation-packages) | Keep product literals out of foundation packages | `composer test:arch` |
| [PKG-008](constitution.md#pkg-008--include-every-package-in-the-monorepo-checks) | Include every package in the monorepo checks | Package enumeration and CI matrix |
| [PKG-009](constitution.md#pkg-009--retired-historical-import-export-directory-rule) | Retired historical import-export directory rule | Retired |
| [STR-002](constitution.md#str-002--keep-each-use-case-in-one-feature-module) | Keep each use case in one feature module | Module layout gate and use-case review |
| [STR-003](constitution.md#str-003--the-package-root-is-its-root-module) | The package root is its root module | Module layout R0–R3 |
| [STR-004](constitution.md#str-004--give-directories-a-boundary) | Give directories a boundary | Directory boundary review |
| [STR-005](constitution.md#str-005--scope-internal-to-its-module) | Scope Internal to its module | Package graph R2 and module layout R4/R5 |
| [STR-006](constitution.md#str-006--place-public-categories-directly-under-their-module) | Place public categories directly under their module | Module layout R1–R3 |
| [API-001](constitution.md#api-001--classify-each-public-symbol-at-its-declaration) | Classify each public symbol at its declaration | PHP API snapshots and package graph R4/R4b |
| [API-002](constitution.md#api-002--internal-types-carry-no-backward-compatibility-guarantee) | Internal types carry no backward-compatibility guarantee | PHP API gate and package graph R2 |
| [API-003](constitution.md#api-003--require-a-declared-symbol-for-cross-package-imports) | Require a declared symbol for cross-package imports | Package graph R1/R4/R4b/R5 |
| [API-004](constitution.md#api-004--keep-foreign-internal-namespaces-private) | Keep foreign Internal namespaces private | Package graph R2 |
| [API-005](constitution.md#api-005--declare-public-maturity) | Declare public maturity | PHP API snapshot and changelog review |
| [API-006](constitution.md#api-006--preserve-compatibility-within-a-supported-major-version) | Preserve compatibility within a supported major version | Compatibility review |
| [API-007](constitution.md#api-007--deprecate-before-removal) | Deprecate before removal | Contract guard and deprecation review |
| [API-008](constitution.md#api-008--verify-the-wire-and-php-contracts-independently) | Verify the wire and PHP contracts independently | Wire snapshot, PHP API and golden surface |
| [API-009](constitution.md#api-009--hide-contract-implementations) | Hide contract implementations | Contract guard and review |
| [DATA-001](constitution.md#data-001--assign-a-single-owner-to-every-table) | Assign a single owner to every table | Ownership gate and schema S9 |
| [DATA-002](constitution.md#data-002--limit-migrations-to-owned-tables) | Limit migrations to owned tables | Schema S9, ownership gate and review of ALTER |
| [DATA-003](constitution.md#data-003--route-foreign-writes-through-the-owner) | Route foreign writes through the owner | Table write ownership gate |
| [DATA-004](constitution.md#data-004--name-cross-package-foreign-key-exceptions) | Name cross-package foreign key exceptions | Ownership T4 |
| [DATA-005](constitution.md#data-005--keep-released-migrations-immutable) | Keep released migrations immutable | Golden DDL, schema S8 and release verification |
| [DATA-006](constitution.md#data-006--declare-and-register-new-tables) | Declare and register new tables | DDL and ownership review |
| [DATA-007](constitution.md#data-007--define-a-deterministic-baseline) | Define a deterministic baseline | Schema S1–S9, golden DDL and layout review |
| [RUN-001](constitution.md#run-001--scope-the-current-tenant) | Scope the current tenant | Scope review |
| [RUN-002](constitution.md#run-002--keep-durable-data-serializable) | Keep durable data serializable | Serialization review |
| [RUN-003](constitution.md#run-003--keep-runtime-objects-inside-their-process) | Keep runtime objects inside their process | Runtime boundary review |
| [RUN-004](constitution.md#run-004--inject-the-time-source) | Inject the time source | Runtime globals G2/G3 |
| [RUN-005](constitution.md#run-005--isolate-requests-and-jobs-in-resident-workers) | Isolate requests and jobs in resident workers | Runtime globals G4, singleton and resident-worker tests |
| [RUN-006](constitution.md#run-006--align-scoped-instances-with-the-tenant-lifetime) | Align scoped instances with the tenant lifetime | PostgreSQL scope boundary and singleton tests |
| [CFG-001](constitution.md#cfg-001--give-each-configuration-key-one-class) | Give each configuration key one class | Configuration classification review |
| [CFG-002](constitution.md#cfg-002--read-configuration-at-the-composition-boundary) | Read configuration at the composition boundary | Configuration composition C1–C4 |
| [CFG-003](constitution.md#cfg-003--use-typed-immutable-options) | Use typed, immutable options | Options binding tests and configuration reference |
| [CFG-004](constitution.md#cfg-004--validate-during-application-boot) | Validate during application boot | Runtime validation and config-cache tests |
| [CFG-005](constitution.md#cfg-005--protect-secret-values) | Protect secret values | Runtime validation and secret-redaction reference |
| [CFG-006](constitution.md#cfg-006--use-corex-public-configuration-names) | Use CoreX public configuration names | Public name review |
| [MSG-001](constitution.md#msg-001--classify-each-message) | Classify each message | Classification review |
| [MSG-002](constitution.md#msg-002--declare-integration-events) | Declare integration events | Event declaration review |
| [MSG-003](constitution.md#msg-003--use-the-published-envelope-format) | Use the published envelope format | `CloudEventCodecTest` |
| [MSG-004](constitution.md#msg-004--version-published-event-names) | Version published event names | Event name review |
| [MSG-005](constitution.md#msg-005--exclude-eloquent-models-from-payloads) | Exclude Eloquent models from payloads | Payload review |
| [MSG-006](constitution.md#msg-006--make-at-least-once-consumption-idempotent) | Make at-least-once consumption idempotent | Consumer idempotency review |
| [MSG-007](constitution.md#msg-007--enforce-classification-and-size-limits) | Enforce classification and size limits | Classification and size review |
| [MSG-008](constitution.md#msg-008--publish-only-committed-facts) | Publish only committed facts | Publication and transaction review |
| [EXT-001](constitution.md#ext-001--change-the-closed-extension-set-only-by-decision) | Change the closed extension set only by decision | Packman extender allowlist and review |
| [EXT-002](constitution.md#ext-002--type-registries-by-contribution) | Type registries by contribution | Contribution type review |
| [EXT-003](constitution.md#ext-003--specify-the-registry-contract) | Specify the registry contract | Registry contract review |
| [EXT-004](constitution.md#ext-004--freeze-registrations) | Freeze registrations | Freeze-point review |
| [EXT-005](constitution.md#ext-005--define-deterministic-contribution-order) | Define deterministic contribution order | Ordering review |
| [EXT-006](constitution.md#ext-006--reject-duplicate-extension-ids) | Reject duplicate extension IDs | Duplicate scheduler handler validation at boot |
| [EXT-007](constitution.md#ext-007--record-extension-identity-in-durable-decisions) | Record extension identity in durable decisions | Replay identity review |
| [EXT-008](constitution.md#ext-008--keep-changes-within-the-module-owner) | Keep changes within the module owner | Module ownership review |
| [EXT-009](constitution.md#ext-009--treat-module-renames-as-data-migrations) | Treat module renames as data migrations | Migration review |

## Non-goals

The constitution records the prohibited change and the condition for reconsidering it. The following table preserves the NON register. See the [normative non-goals](constitution.md#non-non-goals).

| Article | Excluded change | Condition for reconsideration |
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
