## Unreleased — invocation contract in the kernel (audit 2026-10-08 P-02)

- `ToolInvoker`, `ToolRequestContext` and tool handlers use `CoreX\Invocation\Contracts\RequestContext` from the kernel; the former `CoreX\Operations\...` names are deprecated aliases of the same types, so no implementation changes are required.

## Unreleased — PHP 8.4 modernization (P09.4)

## Unreleased — kernel audit repairs (2026-10-08)

- Clarify ToolExecutionGuard rollback obligations for local failed effects; prove invalid output, exceptions, retry and receipt replay with real writes. No SPI signature changes.


## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — tool error taxonomy

- Breaking (PLAN3.P06.2, D90/D92/D93, pre-1.0): tool runtime and guard refusals use thirteen registered `tools.*` codes, including approval, visibility, idempotency and unknown-effect refusals. Forwarded operations codes retain their namespace; MCP serializes the registered code.
- Breaking (PLAN3.P06.2): `ToolResult::$outcome` and its constructor now require `ToolOutcome` (`NotExecuted`, `Committed`, `Unknown`); the three former string constants are removed. MCP retains the same three wire outcome strings.
- Schema startup and resource failures carry typed `ToolSchemaFailure::$errorCode`; diagnostic exception text no longer determines tool errors. Guard policy, effect count, approval checks and replay semantics remain unchanged.
- PHP API manifest and Golden records declare the enum additions and DTO type change.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Extracted tools into their own CoreX\Tools package and provider; ToolCatalogue, ToolInvoker, ToolPolicy and schema/execution ports exchange ToolCall, ToolDescriptor and ToolResult DTOs. Execution and schema worker implementations remain Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased

- Schema workers signal readiness after initialization, so slow PHP startup no longer consumes the unchanged 100 ms validation deadline or changes the 64 MiB memory limit. Startup failures report `schema_unavailable`; validation transport, response, deadline and memory failures report `schema_limit`. All subprocesses are terminated and reaped.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Extracted from CoreX Operations with an independent PSR-4 root, service provider and test suite.
