---
id: DR-2026-10-08-invocation
date: 2026-10-08
status: superseded
source: git commit fb9acbe6826be0f1786bea5da4efa5a4a605fe43
---

# Moving the operation handler contracts to the kernel

On October 8, 2026 the owner approved moving four operation types into the kernel and temporarily keeping six deprecated aliases. The branch review confirmed the exact aliases below. On November 17 the owner approved a breaking redesign before first use. The aliases have since been removed. Consumers use the canonical kernel names.

| Historical alias | Kernel name |
|---|---|
| `CoreX\Operations\Definition\Contracts\OperationHandler` | `CoreX\Invocation\Contracts\OperationHandler` |
| `CoreX\Operations\Invocation\Contracts\RequestContext` | `CoreX\Invocation\Contracts\RequestContext` |
| `CoreX\Operations\Authorization\Contracts\Principal` | `CoreX\Context\Contracts\Principal` |
| `CoreX\Access\Contracts\Principal` | `CoreX\Context\Contracts\Principal` |
| `CoreX\Access\Enums\PrincipalType` | `CoreX\Context\Enums\PrincipalType` |
| `CoreX\Operations\Invocation\Data\InvocationMetadata` | `CoreX\Invocation\Data\InvocationMetadata` |

## Dependency and identity decisions

The aliases were registered through Composer `autoload.files` in `packages/operations/src/Invocation/Internal/deprecated-aliases.php` and `packages/access/src/Internal/deprecated-aliases.php`. FlexCRM could then move Operations to `require-dev` and `suggest`. The package graph forbids a production dependency from `flex/crm` to `axiomasoft/corex-operations`. Tools and Webhooks remain extensions of Operations.

Access previously defined a principal with `principalType()` and `principalId()`. Operations had a separate principal with `id()`. An intermediate implementation moved the latter to `CoreX\Invocation\Contracts\Principal`, duplicating the kernel identity. The accepted design puts `Principal` and `PrincipalType` in Context, alongside tenant and impersonation identity. `RequestContext::principal()` returns the canonical principal. The unpublished intermediate interface was removed.

Access checks, grant lookups, seeders and PostgreSQL tests use the pair of type and ID. The type distinguishes identities with equal ID values across `user`, `agent` and `api_key`. Existing Access implementations, including `PrincipalIdentity`, could be passed to operations directly. Operations callers using `id()` had to adopt `principalId()` and `principalType()`. At the time, repository callers of `id()` were test fixtures.

The review considered three other designs. Extending the Access principal from a kernel interface with `id()` would retain the ambiguous ID and affect parameter variance for `AccessChecker` implementations. Passing a plain `principalId` through `RequestContext` would discard the principal type. Reusing `Audit\Enums\ActorType` would introduce `system` and `support`, which are valid audit actors but cannot hold roles.

## Historical compatibility exception

Thirty-four handlers accepted `RequestContext` as a parameter. Making its former name a subinterface would narrow the parameter and cause PHP signature errors. The six aliases preserved runtime type identity during the transition.

Static analysis could not fully verify the alias behavior. Roave reported deleted classes and non-contravariant signatures, while `DeprecatedInvocationAliasesTest` and `DeprecatedPrincipalAliasesTest` checked runtime compatibility. The former BC gate listed the specific exceptions until its removal on October 8. PHPStan did not issue alias deprecation warnings, so the Operations and Access changelogs documented the migration. The exception applied only to the listed names.
