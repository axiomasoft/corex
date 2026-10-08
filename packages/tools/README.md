# CoreX Tools

Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection.

```bash
composer require axiomasoft/corex-tools
```

Requires PHP 8.4 and Laravel 13. An operations extension: it builds on axiomasoft/corex-operations and requires its runtime. Handlers and request context use the kernel `CoreX\Invocation` contracts.

## Configuration

Register `CoreX\Tools\ToolServiceProvider` alongside `CoreX\Operations\ApiServiceProvider`. Tool admission uses the host bindings for trusted context, authorization and guarded execution. Install `opis/json-schema` when offline JSON Schema validation is required.

## Public contracts

Declare tools through the existing module `Api::tool()` extender and compile the module registry. Exposed tools invoke the declared operation; headless tools require an explicit guarded handler. See the [operation contract](../operations/README.md).

## Failure handling

Unknown tools and rejected authorization fail before handler execution. Missing schema capability or a worker that cannot become ready within its 5-second startup budget returns `schema_unavailable`. After readiness, each validation has a separate 100 ms deadline, including request transfer, and a 64 MiB worker memory limit; exceeding either bound returns `schema_limit`. Malformed worker responses fail closed, and workers are terminated and reaped on success and failure. Correct the capability or declaration before retrying.

Local `domain-key` host guards own rollback as well as approval and receipts.
Run the validated effect callback and receipt write in one transaction. If the
callback returns a non-committed result, force rollback before returning
`not-executed`; a normal return from `DB::transaction` commits even on denial.
Handler exceptions may already be converted into safe callback results.
For provider effects, preserve `committed` or `unknown` and reconcile with the
same operation key; a local rollback cannot undo a network effect. The package
regression fixture demonstrates rollback and committed receipt replay with real
DB writes.
