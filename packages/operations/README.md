# CoreX API

An optional operation boundary for Laravel modules. A module declares supported operations;
the platform dispatches only those operations and checks installed module activation against
the current account on every request. Handlers map input to their owning Actions or queries.
There is no implicit CRUD and no discovery of arbitrary controller methods.

The local `operation-contract/1` freeze covers query, command and async command
declarations plus HTTP/exposed-tool parity. The compiled module registry is the
single declaration authority. `ApiProfile` and the five `api/v1` snapshot files
are deterministic code projections; account activation and actor authorization
remain live per invocation. GraphQL is outside this contract.

Requires PHP 8.4+ and Laravel 12 or 13, `axiomasoft/corex` and `axiomasoft/corex`. Physical tenancy is
provided separately by the application; this package has no dependency on an identity product,
role engine, application user class or a particular tenancy implementation.

## Explicit installation

Install `axiomasoft/corex-operations` together with the compatible `axiomasoft/corex` operation-declaration version.
Add `CoreX\Operations\ApiServiceProvider::class` to the application's provider list. The Composer
package deliberately has **no** `extra.laravel.providers` entry. Once this provider is
installed, package routes are enabled by default under `/api`. Set `routes.enabled` to
`false` before keeping a handwritten mount. Prefix and extra middleware come from
`routes.prefix` and `routes.middleware`.

Installed order, before the operation wildcard:

```text
GET  /api
GET  /api/{version}/capabilities
GET  /api/{version}/openapi.json
POST /api/v1/bulk-operations
GET  /api/v1/bulk-operations/{id}
POST /api/v1/bulk-operations/{id}/cancel
GET  /api/v1/bulk-operations/{id}/result
GET  /api/v1/bulk-operations/{id}/failures
GET/POST   /api/v1/webhook-subscriptions
PATCH      /api/v1/webhook-subscriptions/{id}
POST       /api/v1/webhook-subscriptions/{id}/disable
GET        /api/v1/webhook-deliveries
GET        /api/v1/webhook-deliveries/{id}
GET        /api/v1/webhook-deliveries/{id}/attempts
POST       /api/v1/webhook-deliveries/{id}/replay
POST       /api/v1/webhook-deliveries/replay
ANY  /api/{version}/{path?}
```

`OperationErrorBoundary` wraps only the operation wildcard. Discovery does not dispatch an
operation. No route reads account, workspace or principal from the payload. Async
commands answer `202` with one `Location`. Deprecation headers come only from the
operation descriptor.

## Declare operations

Use the existing `Api` extender in the **owning module** manifest:

```php
Api::operation(
    id: 'notes.create',
    method: 'POST',
    path: '/notes',
    handler: CreateNoteOperation::class,
    ability: 'notes.create',
    requestSchema: [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['title'],
        'properties' => ['title' => ['type' => 'string', 'maxLength' => 100]],
    ],
    responseSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
    rules: ['title' => ['required', 'string', 'max:100']],
    version: 'v1',
    successStatus: 201,
)
```

Compile with the existing `corex:modules:compile` command. The compiler clones declarations,
stamps manifest ownership, rejects invalid paths/versions, duplicate version-scoped operation IDs,
and equivalent method/path templates. Equally specific overlapping routes are rejected;
equivalent templates across methods must use the same parameter names. Old compiled resource rows rehydrate with defaults;
`Api::entity()` and `Api::extendResource()` remain metadata and create no executable endpoint.
A new operation handler must implement `Contracts\OperationHandler`; the API projection checks
this before dispatch or OpenAPI export. The lower module compiler does not depend on the API package.

Use literal path segments or `{id}` parameters, each parameter once. Literal routes take
precedence over parameter routes. Only declared methods execute; unknown method/path/version is 404.
Success must carry an envelope, so 204 is not supported. Use a new operation/API version for a
breaking request or response change. Public interface changes follow the package's SemVer policy.

```php
final class CreateNoteOperation implements \CoreX\Operations\Definition\Contracts\OperationHandler
{
    public function __construct(private readonly CreateNote $action) {}

    public function handle(
        \CoreX\Operations\Invocation\Contracts\RequestContext $context,
        array $input,
        array $parameters,
    ): array {
        // DTO mapping lives here; domain invariants and transactions remain in the Action.
        $note = $this->action->handle(new NoteData(title: $input['title']));

        return ['id' => $note->id];
    }
}
```

`rules` are serializable Laravel string rules, checked at runtime. `requestSchema` and
`responseSchema` are module-authored JSON Schema used for documentation/client generation;
the default runtime does **not** implement a complete JSON Schema validator or coerce input.
Keep schema and rules consistent in the same declaration and prove domain constraints in module tests.
GET uses query input. Mutations accept a JSON object body and no query parameters. Undeclared
input fields fail with 422; path parameters are separate from validated input. Handlers must
validate parameter formats and scope every resource query to the trusted context, returning
404 for null/foreign/hidden resources. Never serialize an entire model by default.

## Identity, policy and trusted context

Bind the public interfaces in the application:

| Interface | Consumer responsibility |
|---|---|
| `PrincipalResolver` | Resolve session/token/OIDC/service authentication to `Principal::id()`. No actor claims from body/query. Default returns null. |
| `OperationAuthorizer` | Map the module's declared `ability` to the application's policy for this principal and account/workspace. Default denies. |
| `ContextResolver` | Consume already initialized account state, optionally verify/narrow workspace using the authenticated principal. Default consumes `TenantContextResolver` and `TenancyManager` afresh. |
| `RequestContext` | Typed tenant, optional workspace, principal and request metadata. Never derive account or connection from request input. |
| `RequestContextProvider` | Read the admitted typed context from an Action adapter during dispatch only; fails closed outside an operation. |
| `OperationHandler` | Explicit Action/DTO mapping or scoped read projection. No generic write bypass. |
| `ResponseSerializer` | Customize serialization while preserving the version's data/error/meta contract, or define an explicitly separate profile/version. |
| `SchemaContributor` | Derive response data schema from the operation declaration; no parallel endpoint catalogue. |

An Action adapter may inject `RequestContextProvider` to consume the **same** admitted principal/context
that the dispatcher passed to its handler. It reads the current Laravel request afresh and is
installed only during handler construction/execution; `finally` restores request attributes after
success or failure. It does not initialize accounts, authenticate again or retain singleton state.
Direct dispatcher calls outside the HTTP kernel must bind their request as Laravel's current request
if their handlers use this provider.

Bindings are transient by default and provider defaults preserve bindings installed beforehand.
Application adapters should be transient or request-scoped; never cache a principal/context in a singleton.
The default physical profile rejects missing/boxed/mismatched manager state with `context_required` 403,
and a non-active account with `account_unavailable` 403. A boxed deployment may explicitly set
`corex-operations.require_physical_tenancy=false`; this does not bypass identity, authorization or module activation.

The default context resolver trusts workspace narrowing performed by the surrounding pipeline.
For token/service authentication, that pipeline must check membership for the **same** identity returned
by `PrincipalResolver`; operation authorization must recheck revocation as required by the product.
Use `axiomasoft/corex-tenancy`'s additive `Contracts\PrincipalWorkspaceResolver::resolveFor($principalId, $slug)`
for this check: it uses the active account database and reads active membership afresh, without `auth()`.
Then call the existing `TenancyManager::setWorkspace($workspace)` and verify the resulting context.
The legacy session `WorkspaceResolver::resolve()` remains supported and delegates to this same query.
An account-wide route keeps a null workspace; permissions must explicitly admit account-wide operations.

The application may implement/decorate `ContextResolver` to choose a validated workspace slug from its
route. Compose the supplied context resolver before and after narrowing; do not copy membership SQL
or change PDO yourself. A typical sequence in an adapter is:

```php
$base = $this->trusted->resolve($request, $principal, $metadata);
$workspace = $this->workspaces->resolveFor($principal->id(), $validatedRouteSlug);
$this->manager->setWorkspace($workspace);
$resolved = $this->trusted->resolve($request, $principal, $metadata);

if ($resolved->tenant()->workspace?->id !== $workspace->id) {
    throw new \DomainException('Workspace context is required.');
}

return $resolved;
```

Here `$trusted` is the original `ContextResolver` binding captured when decorating the container,
not a second resolution of the decorator itself. The application maps this `DomainException`
and `NoDefaultWorkspaceException` to its safe 403 context error at the public error boundary. Slugs may narrow only after principal authentication and membership verification;
headers/body/query may never select an account, connection or actor. Account and session isolation
remain the existing tenancy pipeline's responsibility. End tenancy in its established `finally` lifecycle.

## Envelope and errors

```json
{"data":{"id":"..."},"error":null,"meta":{"request_id":"...","api_version":"v1"}}
```

Errors use `data:null` and `error:{code,message,details}`. `details` is a JSON object. Responses
carry `X-Request-ID` and `Cache-Control: no-store`. A supplied request ID is propagated only if it
matches `[a-zA-Z0-9_.-]{1,128}`; otherwise one is generated. It is correlation metadata, never authority.

| Status | Meaning |
|---|---|
| 401 | Missing/rejected authentication |
| 403 | Authorization denied, required context absent or account unavailable |
| 404 | Unknown operation/version, inactive/uninstalled module, hidden/foreign resource |
| 409 | Explicit module-domain conflict (`ApiError` or HTTP conflict); never guessed from raw SQL |
| 422 | Input or Action validation error |
| 429 | Application rate limit reached |
| Other 4xx | Application HTTP rejection with its status and a safe generic message |
| 500 | Unexpected error; safe message, original exception reported to the application's logger |

Translate domain conflicts to an explicit safe `ApiError('conflict', 409, ...)` in the module handler.
Database/tenancy implementation exceptions are never exposed. A Laravel authorization exception
marked as 404 remains 404. Authorization covers an operation capability, so handlers still enforce
resource visibility and Action invariants. There is no automatic policy, membership or CRM rule.

## OpenAPI

Read the published OpenAPI document produced from the installed-code registry:

```php
$document = json_decode(file_get_contents($publishedOpenApiPath), true, 512, JSON_THROW_ON_ERROR);
```

This is a build-time catalogue, with deterministic route/method order and module/ability annotations.
It includes data/error envelopes, schemas, version metadata and path/query/header parameters.
The identity scheme is application-owned; add its OpenAPI security definition when building your
profile, and generate clients from that resulting document. The discovery schema route grants no operation access and
no principal/module access is granted by this catalogue. Don't expose the complete installed
catalogue as a tenant's authorization listing. This package does not ship a client generator.

## Tool catalogue and policy

Register `CoreX\Tools\ToolServiceProvider::class` alongside `ApiServiceProvider` when exposing the
optional tool surface. It binds `Contracts\ToolCatalogue` and `Contracts\ToolPolicy` only; no routes,
migrations or network calls are installed.

`CompiledToolCatalogue` projects explicit compiled tool declarations on every `discover()` call:
module activation is read fresh from `ModuleRegistry`, consumer `ToolPolicy::discover()` filters the
result, per-tool `availability['discover'] === false` metadata is honoured, and descriptors are
returned in deterministic sorted order with a revision hash over the projected set. More than 1000
matching descriptors yields `catalogue_limit`; the default `DenyToolPolicy` denies both discover and
invoke until the application replaces the binding.

Bindings are transient. The catalogue is not an authority cache: account, activation and policy
changes are visible on the next call. `ToolPolicy::invoke()` is separate from discover.

## Guarded invocation

`ToolInvoker::invoke(RequestContext $context, ToolCall $call)` resolves the exact compiled tool
tuple, validates the expected contract hash, admits and validates arguments against
`tool-schema/1`, checks `ToolPolicy::invoke()`, then delegates to `ToolExecutionGuard` before
calling the owning `OperationHandler`. Handlers receive an additive `ToolRequestContext` carrying
validated `operationKey` and `toolContractHash` metadata; legacy handler signatures are unchanged.

`ToolCall` requires `code`, `toolVersion`, `expectedContractHash`, typed `JsonObject` arguments and
`requestId`. Optional `operationKey` and `clientProof` are untrusted correlation/evidence only.

`ToolResult` reports `outcome` (`not-executed`, `committed`, `unknown`), optional `structuredData`,
safe `errorCode`, optional `operationKey` and `replayed`. Safe error codes include
`tool_unavailable`, `input_invalid`, `tool_revision_conflict`, `approval_required`,
`approval_invalid`, `idempotency_conflict`, `unknown_effect` and `output_contract_failed`.

Default `ReadOnlyToolExecutionGuard` allows read side-effects only. Mutations require a consumer
`ToolExecutionGuard` binding that owns approval proof, idempotency and transactional boundaries.
The generic adapter never interprets client hints as authority and does not provide a generic
approval ledger.

`Internal\McpToolsProjection` provides fake in-memory `mcp-tools-projection/1` transport parity
(`MCP2025-11-25`) over the same catalogue and invoker; it installs no routes or live endpoints.

## Cost admission and explicit schema setup

Declare a recipe on the existing module operation with `->cost(['base' => 1, 'row' => 2,
'max_units' => 10000])`. Allowed integer weights are `base`, `row`, `field`, `relation`,
`attribute_filter`, `highlight`, `side_effect`, and `max_units`, each at most 10000;
base and max_units must be positive. Legacy operations use base=1 and zero other weights.
Bulk operations require an explicit recipe and bounded row counts. Estimation uses admitted
schema fields and declared page limits, checks integer overflow and performs no I/O.

Before enabling cost control, configure two distinct named database connections in
`corex-operations.schema`. Each of `budget` and `tenant` requires `connection`, `driver`, and
`database`, matching the actual configured connection. Every API node must use the same
installation-wide budget store; tenant state remains on the already active tenant connection.
Boxed installations may use two connection names to the same database. Payloads cannot
choose either connection. Table ownership and retention are recorded in
[table-ownership.md](../../docs/architecture/table-ownership.md).

Publish `corex-operations-migrations-budget` and `corex-operations-migrations-tenant`, then apply the new
migrations explicitly to their owning databases using the host's migration procedure.
Provider boot never runs DDL. Configure a host-owned `scope_hmac_secret` and stable
`installation_id`, and set `corex-operations.cost.enabled=true`. The shipped activation flag is
false until setup; it is distinct from the enforcement policy below. Do not treat an
installation with cost control disabled as protected by SQL budgets. Once enabled, a
missing or failed store prevents handler execution; it never silently disables enforcement.

The default policy enforces the global bucket and observes account/client/actor buckets.
Each starts at 10000 units with 1000 units/second refill. `enforced_levels` may add lower
levels and must retain global. These are local safety defaults, not billing units or an SLO.
All four buckets reserve atomically; a denied ancestor leaves every bucket unchanged.
Units use checked integer micro-unit arithmetic internally. Scope keys are HMACs and never
appear in public metadata or metric labels; authentication, idle retention and host request
limits still bound scope cardinality.

Settlement refunds the estimate minus server-observed actual cost. If counters are absent,
actual equals the estimate. Excess actual cost becomes debt. Repeated settlement returns
units once; pre-handler rejection refunds fully. An unknown/crashed or expired reservation
is abandoned without refund. Reservations expire within one hour; expiry proves no absence
of effects. Idempotent replay pays one unit for replay admission, rather than charging the
original handler again. HTTP `meta.cost` and tool metadata carry the same requested/actual,
mode and global throttle fields. Exhaustion returns 429 with the computed `Retry-After`;
store unavailability is an infrastructure error. Host body/rate/concurrency caps remain required.

## Durable bulk operations

`axiomasoft/corex-transfer` is optional (`suggest`), and owns all import/export reads, mapping, writes
and result storage. Bind `Contracts\BulkTransferFactory` explicitly in installed application
code. It creates a transfer job atomically and idempotently using the bulk operation id,
and returns a `Data\BulkTransferSession` with public transfer ports and the current token.
Missing transfer or factory is `capability_unavailable` before submission.

The host's admitted operation adapter must supply the trusted `DataScope`, client identity
and `BulkOperationDefinition`; literal bulk routes consume these bindings, not raw connection
or class names from JSON. Install the tenancy/auth/field-policy pipeline before those routes.
Replace the default result reader's denying field-policy adapter with the host's current policy.
Webhook management routes use the shared dispatcher; the host owns bulk admission composition.

Submit requires an idempotency key and returns 202 with a stable id, `status_url` and
`Location`. Poll returns progress and an execution error separately from admission;
active responses carry `Retry-After: 2`. Pending-row polling is durable authority;
queue notifications may be lost. `corex:operations:bulk-work` advances one bounded step under a
trusted, already active tenant context. Each step rechecks authorization, restore epoch,
contract hash and extension stamp, fences the lease/revision, and budgets a batch separately.
There is no generic arbitrary-class executor or cross-account scheduler.

Cancellation is cooperative: pending work stops before the next target call; a running
operation moves through cancelling to cancelled after at most one bounded batch. Committed
rows remain committed. A completed/failed outcome committed before cancellation wins.
Lease fencing prevents stale database completion; it cannot revoke an external effect.
Unknown outcomes require reconciliation, rather than a blind retry or success counter.
Import recovery resumes the committed checkpoint. An interrupted, unsealed export starts
a new attempt and captures a new complete snapshot; it never resumes across snapshots.
Only sealed parts with verified tenant, current field policy and matching hashes may be emitted.

The qualified transfer maxima are 1 GiB/source, 1 MiB/record, 256 fields, depth 32,
500 rows/batch, 1000 errors, 30 seconds and 128 MiB. Time/memory failure rolls back the
current transactional batch and preserves its checkpoint. Cancellation is checked around
batch boundaries. A single source/target call is not preemptible; worker timeout and process
memory limits remain host backstops. Database rollback cannot undo network effects;
targets need transactional/idempotent writes or their own write-token reconciliation.
Dry-run does not advance source, target or checkpoint.

Input refs are authorized tenant storage ids with hash/length/format or canonical parameters
up to 20 KiB; arbitrary URLs, disk paths and credentials are rejected. Unknown/foreign ids
return the same 404 without reading storage. Owning actor/client or explicit delegation is
required for poll/cancel/results. Failure pages contain at most 100 ordinal/code/path records,
never raw input. Partial valid imports expose `has_failures`; unknown rows never count as success.
Results are available only after completion/sealing (409 before, 410 after expiry), retained
24 hours after terminal state; metadata retains the original outcome for 30 days, then 404.
Result TTL must fit metadata TTL and idempotency safety. Active jobs are never pruned.
A cancelled job is not resurrected; a new submission needs a new idempotency key.

## Webhook delivery, replay and retention

Topics project declared P5 message contracts through explicit `webhooks.topics` and installed
`webhooks.projectors` mappings. Implement `Contracts\WebhookPayloadProjector` with an export
field allow-list. Subscription policy denies personal/restricted topics without sensitive
ability and policy permission, and always denies secret topics. There are at most 100 active
subscriptions per account and 100 matching subscriptions per event. Canonical UTF-8 bodies
are capped at 20 KiB; excessive projection becomes dead with a safe code. Encrypt-at-rest
remains a deployment responsibility; crypto-shredding is not promised.

Fan-out atomically commits the webhook inbox claim and one delivery per subscription/event.
The snapshot uses subscriptions active at first successful materialization. Network I/O is
outside that transaction. Delivery is at-least-once, without ordering or exactly-once guarantees.
Retries and replay preserve delivery id, event id, raw body bytes/hash and destination.
Receivers must commit their dedup inbox and effect together and retain dedup throughout
the retry/replay horizon. ACK loss can resend even after a successful receiver effect.

Remote delivery ships disabled. Activation requires `delivery_enabled=true`, `worker_ready=true`,
an explicitly bound host key ring, secret resolver and ext-curl. Provisioning and actual sends
belong to the host; local qualification grants no production migration, live key rotation or
remote-send authority. `corex:webhooks:deliver --max=100` runs only for the active trusted tenant.
No automatic account traversal or generic localhost exception is installed.

The production URL policy accepts canonical ASCII DNS names on HTTPS port 443, without
credentials, query, fragment or IP literals. Every attempt resolves and checks all A/AAAA
addresses, bounded CNAMEs and organization-denied ranges, rejecting mixed public/private sets,
special-use IPv4 and IPv6 ranges. DNS failure is retryable; policy rejection is terminal.
cURL pins the vetted address before connecting, preserves hostname/SNI, verifies TLS,
disables redirects/proxies and permits no unvalidated fallback lookup. Connect/total timeouts
are 3/10 seconds; response body is discarded with a 64 KiB cap and headers have a 16 KiB cap.
Local TLS tests use a separate test-only transport; no configuration flag weakens production policy.

Standard Webhooks signatures cover `delivery-id.timestamp.raw-body` using HMAC-SHA256 and
`whsec_` plus strict base64 of 32 bytes. Headers are `webhook-id`, `webhook-timestamp`,
`webhook-signature`; each attempt gets a fresh timestamp/signature. Receivers use constant-time
comparison and a ±300-second window, plus durable dedup. Rotation allows two keys with
space-separated signatures during overlap. Confirm receiver readiness for the new key before
revoking the old key; arbitrary 2xx is not that confirmation. Secrets, signatures, raw response
and raw payload never belong in logs, attempts or management API responses.

A delivery lease is 30 seconds. 2xx succeeds; 410 disables the subscription, redirects are
terminal; 408/425/429, 5xx and network failures retry. Other 4xx are dead. Full jitter starts
at 5 seconds, caps at one hour, and has a one-second minimum; valid Retry-After is capped
at 24 hours. A cycle ends after 12 attempts or 72 hours from its start. An unknown send stays
unknown in history, and a stale worker cannot ACK a newer claim.

Management mutations require the declared `webhooks.manage`/`webhooks.replay` scopes, current
authorization and idempotency. Lists/attempts use scoped cursors capped at 100. Replay permits
only terminal deliveries, at most three cycles within seven days; replay of succeeded work
needs explicit intent and audit reason. Range replay requires a subscription and a UTC window
up to 24 hours, at most 100 selections, a fixed upper bound and a continuation cursor.
Parent idempotency returns the same selection/outcome. Changed/disabled endpoints, revoked
permissions, removed topics and expired bodies refuse replay. A new business effect needs a
new domain event; replay deliberately keeps the original identity.

Terminal webhook bodies remain seven days and metadata/attempts 30 days. Shorter PII body
retention must shorten replay horizon with it. Active rows are never pruned. Dedup tombstones
and the P5 webhook inbox must survive every possible replay of the original event; the inbox
default is indefinite. `corex:webhooks:prune --max=100` cleans webhook bodies, attempts and inbox history
only in an already restored trusted tenant scope. `corex:operations:prune --max=100` cleans bulk results
and shared budgets, checks bulk ref ownership/hash, and never refunds unknown reservations or removes held bucket keys.

`WebhooksServiceProvider` is discovered from the package manifest. Its `corex-webhooks` config
contains the existing webhook options; the engine config no longer owns them. Contract snapshots
include webhook documents through tagged `ContractSnapshotSection` contributors.

## Verification

`composer test`, `composer analyse`, `composer test:coverage` (80% floor, coverage driver required).
Pure/package tests use SQLite `:memory:` and disposable migrations. SQL qualification also
requires PostgreSQL `*_test` after isolation preflight; never count a missing/skipped lane as green.
The API has no package `test:pg` script: its declared PG lane is the root Pest configuration
restricted to `packages/operations/tests/Feature --group=pg`, with explicit `PGSQL_*` test settings.
Several earlier PG proofs are untagged and require those settings during the full API suite too.
Transfer additionally provides `composer --working-dir=packages/transfer test:pg`.
Real principal/workspace membership isolation is verified in the owning tenancy suite.
From the monorepo root, `tests/Feature/OperationContractTest.php` covers real
routes and exposed tools, `tests/Arch/ApiContractTest.php` guards the one-authority
boundary, `corex:operations:snapshot api/v1 --fixture=tests/fixtures/public-error-contract.php --check`
checks exact bytes, and `composer api-contract` checks intra-major compatibility. `webhooks.json`
extends the same generator and manifest; planted topic/payload/PII/signature/id/major/cost-default
changes must fail the compatibility gate. Current commands, input/snapshot/output hashes,
all 16 threat-matrix rows, repaired failures and limits are recorded in the
[P14 qualification receipt](../../plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P14-execution-validation.md).
