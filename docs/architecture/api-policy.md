# CoreX public API policy

**Version** 1.0.0 · **Status** Normative · **Level** 0 (`corex/*`)

This policy implements articles `API-001` through `API-008`. The constitution defines the invariants. The taxonomy defines the maturity levels in `extra.corex.stability`. [Constitution](constitution.md) · [Taxonomy](taxonomy.md)

## Declaring the public surface

A class, interface, trait or enum declares its supported use through exactly one attribute on the declaration. Attributes belong to `Packman\Attributes`. Public types use `#[Api(since: '1.0')]` or `#[Spi(kind: SpiKind::Implement|Call, since: '1.0')]`. Attributes are not inherited. The `since` value records the version that introduced the symbol. Publication of a release requires separate evidence. An `@api` tag, a directory name or generated JSON cannot authorize an import.

Every type under `Internal` must explicitly declare `#[Internal]` and cannot also declare `Api` or `Spi`. Types outside that directory can use the same attribute to mark private implementation.

| Layer | Supported use |
|:--|:--|
| Api | Call, read or catch. Consumers cannot implement or extend the type. |
| Spi Implement | Implement or extend the documented contract, including its protected hooks. |
| Spi Call | Call, subscribe or integrate as documented. |
| Internal | Implementation of the owning package only. No backward-compatibility guarantee. |

`composer php-api` runs `scripts/php-api.php --strict --check`. It checks source classifications, all reachable native and PHPDoc types, immutable DTOs, imports and snapshot freshness. API payloads exclude models and collections. Public and SPI signatures must exclude Internal types, including the package's own. Model extension SPI is allowed only on the documented Laravel surface. The product is checked as a consumer.

The generated `packages/<name>/resources/contracts/php-api.json` and public reference share the source classifications. Packman serializes JSON. CoreX checks the complete type graph and generates the Markdown reference. Native JSON stores `layer: spi` and a separate `spi_kind`. External inherited members stay in the source-graph checks and are omitted from the JSON. The package graph uses the same index of declaring owners. R4 checks individual symbols, R4b checks classification and snapshot freshness, and R1/R2/R3/R5 remain independent. The graph enforces package boundaries under the shared `CoreX` namespace. A PHPDoc `@internal` tag does not enforce them. [Public API reference](../reference/public-api.md)

The SemVer policy defines the layers and selects the relevant contracts independently at the base and candidate revisions. Manifests no longer carry namespace metadata. JSON cannot grant public status to an unknown or Internal symbol. [SemVer policy](semver-policy.md)

## Maturity transitions

The `stability` table defines the guarantees of `extra.corex.stability`. [Taxonomy](taxonomy.md)

| Transition | Requirements |
|:--|:--|
| `experimental` → `stable` | CI passes the compatibility diff, the supported symbols were unchanged in the last minor release, and an external consumer confirms the package works. |
| `stable` → `deprecated` | Name the replacement and the major release that will remove the package. Record both in the package's `CHANGELOG.md`. |
| Any level → `internal` | Remove the package from the distribution list and notify consumers. |
| `deprecated` → removal | Remove only in a major release, at or after the announced version. |

A stable package cannot return to `experimental`. Withdrawing the stability guarantee requires a major release.

## Breaking changes

Only additive changes are allowed within a supported major version. New parameters must be optional and their defaults must preserve existing behaviour. The following changes are prohibited.

1. Remove a public symbol, method, property or constant.
2. Remove or rename a field of a public data type.
3. Add a required parameter or make an optional parameter required.
4. Narrow a parameter type or widen a return type.
5. Change nullability in either direction in a public signature.
6. Remove an enum case used by a public type.
7. Remove or rename a stable error code, change its category, RFC 6901 `path` semantics or `retryable` value, or remove a documented detail key.
8. Change pagination or the ordering of results.
9. Remove a webhook topic or change its payload without a new version.
10. Move a public symbol to another namespace without preserving the old symbol.

Compatibility checks must cover this list. Any rule without a machine check must be identified explicitly.

## Public API v2 contract

The compiled Packman `ModuleRegistry` contains operation and tool declarations. `OperationRegistry`, `ApiProfile` and the `api/v2` snapshots project these declarations. The profile hash reflects installed code and contract settings. The invoker evaluates account and workspace activation on each call.

HTTP serves operations under `/api/v2`. The host connects an MCP endpoint with the same principal and client checks. Exposed tools and HTTP share an `OperationInvoker`. Before invoking the handler it checks activation, scope, action permissions, record access and field access. Reusing a key for the same logical request returns the original result after checking current permissions for the action, record and returned fields. Replay restores accounting from the original `BudgetReceipt` without charging again. Reusing the key for a different logical request returns a conflict. A cursor binds the version, scope, ordering and signing key.

OpenAPI publishes `Idempotency-Key` for operations that accept it and marks it required when declared. Exposed tools validate arguments against the published schema before execution. For an unknown outcome, MCP returns `outcome: unknown` with the original Problem code. Repeating the request with the same key reads or reconciles the original outcome.

Successful JSON responses contain `data` and `meta`. Errors use stable descriptors and RFC 9457 Problem Details. An async command returns 202, a `Location` header and a matching `data.status_url`. Result links and per-row errors require a fresh check of owner permissions. A conditional metadata request checks current authorization before computing a private ETag or returning 304. The metadata policy provider supports `FieldUseSnapshot` so all disclosure decisions come from one fresh owner snapshot. Search checks index readiness before taking the snapshot and verifies its generation again inside it. A host unable to supply the snapshot must withhold metadata.

The five published files under `api/v2` are `errors.json`, `manifest.json`, `openapi.json`, `operations.json` and `webhooks.json`. Earlier versions are available in Git history. Verification uses `tests/Feature/OperationContractTest.php`, `tests/Arch/ApiContractTest.php`, `corex:operations:snapshot --check` and `composer api-contract`. Changes to the public surface require a compatibility decision. Passing these checks alone does not authorize release or baseline updates.

`scripts/check-api-contract.php` compares the parsed `operations.json` and `errors.json` against the committed baseline. OpenAPI is the HTTP projection. The generator writes snapshots in a separate step. Deprecation does not permit removal within the same major version.

| Rule | Breaking change | Allowed addition |
|:--|:--|:--|
| `operation-removal` | Remove an operation ID or change its method or path | New operation |
| `response-field-removal` | Remove a response field | New response field |
| `required-input-addition` | Add a required input field | Optional input field |
| `type-change` | Change `type` | — |
| `nullability-change` | Change `nullable` in either direction | — |
| `enum-removal` | Remove an enum value | New enum value |
| `error-contract-drift` | Remove a code, change its category/path/retryable value or remove a detail key | New code or detail key |
| `pagination-reinterpretation` | Change the envelope, cursor format or ordering ID | — |

The gate rejects unknown snapshot or schema keys with `unknown-schema` and a JSON Pointer. The same generator and manifest include `webhooks.json`. The gate also rejects topic removal, new required payload input, response-field removal, type or nullability changes, widening PII exposure, changed signature or ID semantics, rebinding a major version and changing default cost enforcement. New optional topics and fields are allowed. Regeneration requires comparison with the previous candidate and a byte-for-byte check. An empty topic list grants no topic declaration or authorization.

## API cost and bulk-operation contract

`operation-cost/1`, `bulk-operation/1` and `webhook-delivery/1` use the compiled module registry, operation invoker, messaging contracts, scope and clock. Declare costs only through `Api::cost()`. The legacy defaults are base 1, all other weights 0 and `max_units` 10000.

```text
base + row × max(pageRows, exportRows) + field × selectedFields
     + relation × relationNodes + attribute_filter × attributeFilters
     + highlight × flag + side_effect × sideEffects
```

Structural limits and overflow checks apply before the handler, including in observe mode. Bulk operations need an explicit recipe and bounded input.

When cost control is enabled, the global budget always enforces its limit. Account, client and actor budgets default to observe. Capacity 10000 and refill 1000 units/second are local defaults, without pricing or SLO implications. The shipped `cost.enabled=false` requires explicit setup and activation. Disabled cost control provides no observation or protection. After activation the handler requires a shared SQL authority. Every node uses one installation budget store. Tenant payload stays physically tenant-local. Both explicit connection identities are checked before SQL. Budget scopes come from trusted context, keys use HMAC, and metrics omit IDs.

Reserve atomically across every ancestor before the effect. Terminal settlement returns unused units once. Actual cost above estimate creates debt. In the absence of server counters, actual cost equals the estimate. Rejection before the handler returns the estimate. A crash, unknown outcome or expiry gives no refund. Replay costs one base unit. HTTP and tools expose matching metadata. A 429 carries a calculated Retry-After. Infrastructure failures must remain distinct from exhausted budgets.

Transfer owns import and export. An optional factory installed through host DI uses the public transfer ports. The API cannot write another owner's tables or execute class names from input. Literal bulk routes require an admitted scope, client and definition, plus a host authorization and field-policy adapter. At each bounded step the worker rechecks scope, authorization, restore epoch and extension stamp. Polling pending rows permits recovery from queue loss. Cancellation waits at most one bounded batch and retains committed effects. Import resumes from its committed checkpoint. Restarting an unsealed export starts a fresh, complete capture. Unknown outcomes require reconciliation. Transaction rollback cannot undo network effects. Reading a result checks current scope, field policy and hash. Foreign IDs return 404.

The transfer profile allows 1 GiB per source, 1 MiB per row, 256 fields, depth 32, batches of 500, 1000 errors and 30 seconds of runtime. The default memory limit is 128 MiB. Hosts may set `corex-import-export.limits.max_memory_mib` from 1 to 1024 MiB for the entire PHP process. Checks are cooperative. An individual read or write cannot be preempted. Hosts must enforce process, edge, body, request-rate and concurrency limits. Input parameters are limited to 20 KiB and failure pages to 100 entries. Result TTL is 24 hours, within the metadata TTL of 30 days. Active jobs cannot be pruned. Retention preserves cancellation and unknown outcomes. Billing, GraphQL, hard real-time limits, universal exactly-once execution and cross-account scheduling are outside this contract.

The table registry records schema ownership, PII and retention. The host explicitly applies migrations published under the budget and tenant tags. Boot must not run DDL. Remote webhook delivery defaults to disabled and requires key, worker and transport readiness. Local grants and synthetic tests cannot authorize production migrations, key rotation or live sends. The package guide covers setup, endpoint semantics and host responsibilities. [Table ownership](table-ownership.md) · [API README](../../packages/operations/README.md)

## Error contract

`ErrorCategory`, `UnavailabilityReason`, the immutable `ErrorDescriptor` and the `DescribesError` interface form the shared vocabulary. Each package owns its error codes. There is no central code enum or common `CoreXException` hierarchy. An exception implements the interface at the public boundary. A typed result can carry the same descriptor.

Stable fields are `code`, `category`, RFC 6901 `path`, `retryable` and documented `details` keys. Messages, exception classes and stack traces are outside the contract. The transport maps the descriptor to an HTTP status.

A `path` is `null` or a JSON Pointer. An empty string identifies the document root. A nonempty path starts with `/` and permits only `~0` and `~1` escapes. `retryable=true` means that the same logical input might succeed later. Callers must check idempotency and `Retry-After` before retrying.

| Reason | Meaning | Category | Default retryable |
|:--|:--|:--|:--|
| `capability_unavailable` | Package, provider or backend is missing or unavailable | `unavailable` | Producer decides |
| `capability_disabled` | Configuration or activation disables an installed capability | `unavailable` | `false` |
| `operation_unsupported` | The selected driver does not implement the operation | `unsupported` | `false` |
| `access_denied` | Actor permissions or policy deny the action | `authorization` | `false` |
| `feature_not_entitled` | The authoritative edition or license excludes the feature | `authorization` | `false` |

Keep the five reasons distinct. Emitting `feature_not_entitled` requires an authoritative entitlement source. It does not introduce a sixth status.

Removing or renaming a code, changing its meaning, category, path semantics or retryability, or removing a documented detail key breaks the error contract. A new code, a new optional detail key or a more precise human-readable message is additive.

| Situation | Form |
|:--|:--|
| Expected business denial | Typed result or decision with a stable reason |
| Normal query absence | Nullable or optional result |
| Programmer or configuration defect | Typed exception |
| Infrastructure failure | Typed exception with a safe descriptor |
| Optimistic or concurrent conflict | Domain conflict exception or explicit typed conflict |
| Batch partial failure | Report with per-record descriptors that preserves successes |

## Cross-package imports

A consumer may import an Api or Spi symbol only if it declares the owning package as a dependency and the dependency direction is allowed. Foreign Internal symbols fail R2. Unknown or unmarked symbols fail R4. Unclassified declarations fail R4b. A stale manifest yields `API-STALE` and source errors still fail the checks. The finite set of named composition edges is checked independently. These edges grant no additional public access.
