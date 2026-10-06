# CoreX Transfer

`axiomasoft/corex-transfer` provides narrow, typed ports for bounded CSV/NDJSON transfers. A consumer supplies its source, mapper and target through dependency injection; it owns domain mappings and credentials. The package never discovers connectors or applies migrations automatically.

Use `TransferRunner::step()` with a trusted `DataScope`, immutable source manifest and current checkpoint token. A resume refuses hash, schema, restore-epoch or lease-epoch drift. `dryRun` evaluates parsing and conflict decisions without writing target, checkpoint, reference or outbox state.

## Export manifests

`ExportSource` is an explicit consumer-provided port. `DatabaseExportRunner` seals only a
`coherent` manifest: every page is canonical NDJSON, persisted as a hash-addressed immutable
part, then read back and verified before the manifest is sealed. An interrupted unsealed attempt
is aborted rather than resumed. Emission rereads only a sealed part and rechecks the current
`DataScope` policy and restore epoch before returning bytes.

The raw NDJSON format is the reversible machine format. `CanonicalExportCodec::displaySafeCsv()`
is deliberately separate and labels the spreadsheet-formula-prefix profile; it is not a raw
round-trip or a universal spreadsheet safety promise. Object-store rename is never assumed: an
existing part is accepted only when its bytes still match its declared SHA-256.

## Installation

Install with `composer require axiomasoft/corex-transfer` through the configured CoreX channel. Bind the consumer source, mapper and sink and apply the package-owned transfer schema explicitly before durable runs. The host supplies the current trusted tenant scope; installing the provider does not discover connectors.

## Configuration

`corex-transfer.limits` defaults to 1,048,576 bytes per record, 256 fields and batches of 500 rows. Select bounded limits for the input and retain immutable source/manifest and checkpoint identities across retries. Consumer-owned credentials belong in the application integration, not the transfer payload.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
