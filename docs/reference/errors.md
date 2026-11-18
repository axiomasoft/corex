# Typed package errors

Error identities are package owned backed enums implementing
`Packman\Errors\Contracts\ErrorCode`. The wire value is `<package-slug>.<code>`;
the kernel owns `core.*`. `ErrorDescriptor::code` and `ApiError::errorCode` remain
strings on the wire, while their constructors require an `ErrorCode` object.
Unscoped constructor strings and compatibility aliases are unsupported.

```php
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;

throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Input validation failed.');
```

Each package contributes `ProblemDefinition` values through a
`ProblemDefinitionProvider`. Its service provider registers the contribution during
`register()` using `ProblemDefinitions::register($app, $composerManifest, $provider)`.
Composer metadata and the provider/enum source locations establish ownership. A code
prefix supplied by an enum alone does not grant ownership. The kernel contains no
list of optional packages.

The kernel compiles the registry once at boot. Duplicate codes, duplicate type URIs,
foreign prefixes or enum declarations, invalid statuses and malformed definitions
throw a typed `ProblemRegistryException`. Repeating an already installed contribution
is idempotent; introducing a new contribution after compilation fails. The public
`ProblemRegistry` offers read operations only:

| Operation | Result |
| --- | --- |
| `get(ErrorCode)` | Definition for the exact registered enum identity |
| `fromWire(string)` | Validated definition; unknown values throw a typed error |
| `owner(ErrorCode)` | Owning Composer package |
| `all()` | Deterministically ordered definitions |
| `assertOccurrence(ErrorDescriptor, ?int)` | Checks category, retryability, detail keys and status projection |

Definitions have a safe default title, a default status in 400–599, category,
retryability, allowed detail keys, and `urn:packman:problem:<scoped-code>` as their type.
Occurrences retain their bounded details and RFC 6901 path. A retryable definition
can also have a terminal occurrence; this never schedules a retry. Status remains an
occurrence projection: validation statuses may differ within their category, and an
authorization denial may use 404 to conceal resource existence.

Package exceptions implement `DescribesError`; runtime exceptions can extend
`CodedException`. The mapper uses the registered definition and safe category text,
rather than exposing an exception's diagnostic message. An unexpected throwable or
an unregistered/invalid occurrence maps to `core.internal_error`.

`StoredOperationOutcome::fromCanonicalJson($json, $registry)` restores persisted
identity through `fromWire()`. Its `error($registry)` reconstructs an `ApiError` with
the registered enum. Unknown codes and invalid occurrence values become a safe
registered internal error. Noncanonical or structurally malformed JSON remains an
invalid input and is handled by the operation error boundary.

Run `composer error-registry` to check the catalogue and constructor literal gate.
Aliases, grouped imports and fully qualified constructor names use AST name
resolution; constructor types reject dynamic raw strings. HTTP representation and
negotiation follow the existing operation transport policy.
