# CoreX Tools

Guarded tool runtime for CoreX operations: schemas, contract hash and MCP projection.

```bash
composer require axiomasoft/corex-tools
```

Requires PHP 8.4 and Laravel 12 or 13. Uses CoreX Operations through its public contracts.

## Configuration

Register `CoreX\Tools\ToolServiceProvider` alongside `CoreX\Operations\ApiServiceProvider`. Tool admission uses the host bindings for trusted context, authorization and guarded execution. Install `opis/json-schema` when offline JSON Schema validation is required.

## Public contracts

Declare tools through the existing module `Api::tool()` extender and compile the module registry. Exposed tools invoke the declared operation; headless tools require an explicit guarded handler. See the [operation contract](../operations/README.md).

## Failure handling

Unknown tools and rejected authorization fail before handler execution. Missing schema capability returns `schema_unavailable`; schema workers enforce memory and deadline bounds. Correct the capability or declaration before retrying.
