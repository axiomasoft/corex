# CoreX API

An optional operation boundary for Laravel modules. A module declares supported operations;
the platform dispatches only those operations and checks installed module activation against
the current account on every request. Handlers map input to their owning Actions or queries.
There is no implicit CRUD and no discovery of arbitrary controller methods.

Requires PHP 8.3+ and Laravel 12 or 13, `corex/core` and `corex/modules`. Physical tenancy is
provided separately by the application; this package has no dependency on an identity product,
role engine, application user class or a particular tenancy implementation.

## Explicit installation

Install `corex/api` together with the compatible `corex/modules` operation-declaration version.
Add `CoreX\Api\ApiServiceProvider::class` to the application's provider list. The Composer
package deliberately has **no** `extra.laravel.providers` entry. Registration provides bindings
and the `corex-api-config` publish tag; it mounts no routes and runs no migrations or enables.

The application explicitly mounts its chosen prefix and middleware. For example:

```php
use CoreX\Api\ApiController;
use CoreX\Api\ErrorBoundary;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/api/{version}/{path?}', ApiController::class)
    ->where('version', 'v[1-9][0-9]*')
    ->where('path', '.*')
    ->middleware([ErrorBoundary::class, /* trusted host/account, status, session/auth, workspace */]);
```

Ensure Laravel's middleware priority preserves the chosen order. `ErrorBoundary` must surround
any tenancy/auth/workspace middleware whose exceptions need the API envelope. It handles thrown exceptions and standard Laravel responses carrying a rendered exception;
a custom exception handler must preserve `withException()` metadata. Middleware returning its own response must conform to the same envelope itself.
Use a route parameter named `version` and a `path` containing the operation path after the prefix.
Prefix, guard, cookies, sessions and deployment topology belong to the application.

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
final class CreateNoteOperation implements \CoreX\Api\Contracts\OperationHandler
{
    public function __construct(private readonly CreateNote $action) {}

    public function handle(
        \CoreX\Api\Contracts\RequestContext $context,
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
`corex-api.require_physical_tenancy=false`; this does not bypass identity, authorization or module activation.

The default context resolver trusts workspace narrowing performed by the surrounding pipeline.
For token/service authentication, that pipeline must check membership for the **same** identity returned
by `PrincipalResolver`; operation authorization must recheck revocation as required by the product.
Use `corex/tenancy`'s additive `Contracts\PrincipalWorkspaceResolver::resolveFor($principalId, $slug)`
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
    throw new \CoreX\Api\ApiError('context_required', 403, 'Workspace context is required.');
}

return $resolved;
```

Here `$trusted` is the original `ContextResolver` binding captured when decorating the container,
not a second resolution of the decorator itself. Convert `NoDefaultWorkspaceException` into a safe
403 context error. Slugs may narrow only after principal authentication and membership verification;
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

Build a document from the same installed-code registry:

```php
$document = app(\CoreX\Api\OpenApi::class)->document('v1', 'https://example.test/api/v1');
file_put_contents($buildPath, json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
```

This is a build-time catalogue, with deterministic route/method order and module/ability annotations.
It includes data/error envelopes, schemas, version metadata and path/query/header parameters.
The identity scheme is application-owned; add its OpenAPI security definition when building your
profile, and generate clients from that resulting document. No public schema route is installed and
no principal/module access is granted by this catalogue. Don't expose the complete installed
catalogue as a tenant's authorization listing. This package does not ship a client generator.

## Tool catalogue and policy

Register `CoreX\Api\ToolServiceProvider::class` alongside `ApiServiceProvider` when exposing the
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

## Verification

`composer test`, `composer analyse`, `composer test:coverage` (80% floor, coverage driver required).
Tests use SQLite `:memory:` configuration and stubbed platform dependencies; no database migration
is needed. Real principal/workspace membership isolation is verified in the owning tenancy suite.
