# CoreX Import / Export

`axiomasoft/corex-import-export` imports and exports application records in bounded CSV/NDJSON batches. A consumer supplies its source, mapper and target through dependency injection; it owns domain mappings and credentials. Import checkpoints and sealed export manifests belong to this package.

Database backups, physical tenant moves and disaster recovery belong to deployment infrastructure. An export contains only the records its source selects; it is not a complete database or media backup. Restore-epoch checks prevent old work from continuing after the host restores data.

## Create and resume an import

Resolve `CoreX\ImportExport\Contracts\ImportExportFactory` and call `openImport()` with the
trusted `DataScope`, a stable UUID `TransferRef`, source, mapper, target, mapping snapshot and
conflict rule. The factory persists the job and returns its stock runner, current token and
result reader. Consumers do not insert job rows or construct classes under `Internal`.

```php
$session = app(\CoreX\ImportExport\Contracts\ImportExportFactory::class)->openImport(
    scope: $scope,
    transfer: new \CoreX\ImportExport\Data\TransferRef($jobId),
    source: $source,
    mapper: $mapper,
    target: $target,
    mapping: $mapping,
    conflicts: new \CoreX\ImportExport\Data\ConflictRule('source-newer'),
);

$receipt = $session->runner->step($scope, $session->transfer, $session->token);
```

Keep the same UUID and immutable inputs across retries. Reopening returns the committed checkpoint;
changing scope, source, mapping, conflict rule or mode under that UUID is rejected. The trusted
scope must identify the currently selected database as `<driver>:<database>`, for example
`pgsql:account_database`. The host's `DataScopeAuthorizer` must allow `transfer.open` as well as
the execution and result-reading actions. Authorization runs before source I/O or job creation. Every session invocation also verifies the live tenant context and exact current database connection; retaining a session across a tenant switch grants no authority. Runners are bound to their job identity and export capture inputs.

`TransferRunner::step()` rejects stale checkpoint/lease tokens, incompatible schemas and restore
epochs, and refuses terminal jobs. `dryRun` still creates durable session metadata; its execution
evaluates parsing and conflict decisions without writing target, checkpoint, reference or outbox state.

The public `SessionUnavailable`, `ImportUnavailable` and `ExportUnavailable` exceptions live in
`CoreX\ImportExport\Exceptions`. Reconcile changed authority or inputs before retrying.

## Export manifests

`openExport()` accepts a trusted scope, stable job reference, consumer `ExportSource`, draft
`ExportManifest` and initial cursor. It returns an `ExportSession` with the stock runner and
current job token. Call its runner's `seal()` with the session job id, then `emit()` with the
sealed manifest and a cursor carrying its parts hash. A successful seal completes the job.

`ExportSource` is an explicit consumer-provided port. The stock runner seals only a
`coherent` manifest: every page is canonical NDJSON, persisted as a hash-addressed immutable
part, then read back and verified before the manifest is sealed. An interrupted unsealed attempt
is aborted rather than resumed. Emission rereads only a sealed part and rechecks the current
`DataScope` policy and restore epoch before returning bytes.

The raw NDJSON format is the reversible machine format. The internal display-CSV codec uses a
separate spreadsheet-formula-prefix profile; the public export session emits canonical NDJSON.
Object-store rename is never assumed: an
existing part is accepted only when its bytes still match its declared SHA-256.

## Installation

Install with `composer require axiomasoft/corex-import-export` through the configured CoreX channel. Bind the consumer source, mapper and sink and apply the package-owned transfer schema explicitly before durable runs. The host supplies the current trusted tenant scope; installing the provider does not discover connectors.

## Configuration

`corex-import-export.limits` defaults to 1,048,576 bytes per record, 256 fields, batches of 500 rows and a total process memory budget of 128 MiB. Hosts with larger Laravel workers can set `corex-import-export.limits.max_memory_mib` to an integer from 1 to 1024. This is the whole PHP process budget, not the batch allocation; leave headroom for the application. Select bounded limits for the input and retain immutable source/manifest and checkpoint identities across retries. Consumer-owned credentials belong in the application integration, not the transfer payload.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
