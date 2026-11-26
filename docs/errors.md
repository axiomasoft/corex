# Errors

## Model

- Every package owns a backed enum of error codes that implements
  `Packman\Errors\Contracts\ErrorCode`. The wire value is
  `<package-slug>.<code>`, for example `tasks.tasks_module_disabled`.
- Exceptions that cross a package boundary implement `DescribesError` and
  return an `ErrorDescriptor`: the code, an `ErrorCategory` (for example
  `Unavailable`, `Conflict`, `Validation`) and whether a retry can succeed.
- Each package contributes `ProblemDefinition` values (title, HTTP status,
  category, allowed detail keys). The kernel compiles them into a
  `ProblemRegistry` at boot and rejects duplicates and foreign prefixes.
- The operations HTTP surface renders problems as RFC 9457 documents whose `type` is
  `urn:packman:problem:<code>`.

```php
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;

throw new ApiError(OperationsErrorCode::ValidationFailed, 422, 'Input validation failed.');
```

## Catalogue

[`reference/errors.md`](reference/errors.md) describes the registry in detail
and lists the codes. `composer error-registry` checks that every code is
declared, owned by its package and free of string literals at call sites.

## Handling failures

- Catch the typed exception or read the descriptor; never match on message
  text. Messages are for operators and may change.
- Retry only when the descriptor says `retryable`; a retryable definition can
  still produce a terminal occurrence.
- Authorization failures surface as Laravel's `AuthorizationException`.
