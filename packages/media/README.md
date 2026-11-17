# axiomasoft/corex-media

Private media references, attachment policy and conversions for CoreX, backed by Vaulter's content-addressed storage. `MediaPolicy` denies every action until the host explicitly permits it. The package exposes no public storage URLs.

`MediaLibrary` stores and reads files; `RelationLookup` records opaque attachment edges to application entities. CoreX owns purpose, authorization, entity relations and conversion scheduling. Vaulter owns all bytes, deduplication, pins and physical garbage collection.

```php
$file = app(\CoreX\Media\Contracts\MediaLibrary::class)->store(
    context: $trustedMediaContext,
    input: new \CoreX\Media\Data\UploadInput(
        requestKey: $idempotencyKey,
        file: \CoreX\Media\Laravel\UploadedFileSource::fromFile($uploadedFile),
        purpose: 'attachment',
    ),
);
```

An upload inserts its owner reference, commits the bytes through `BlobStore` and pins the blob in one `VaulterDatabase` transaction. Retrying the same request returns the same media reference; distinct references to identical bytes have independent pins. Publication or pin failures roll back the owner row. Unreferenced physical objects are Vaulter's cleanup responsibility.

Conversions use the same protocol after the source-version and worker-lease checks. With PHP `ext-gd`, the bounded `thumbnail` profile handles PNG/JPEG; otherwise hosts can register `ConversionDriver` implementations. A conversion becomes visible only after its blob pin and reference commit together. `readConversion(context: $context, fileId: $file->id, profile: 'thumbnail')` returns an authorized `MediaReadRef`, or null while pending. Its `openStream` closure rechecks the current tenant, parent policy and profile before reading bytes. Expired worker claims are reclaimed with a new epoch; stale completions cannot publish. A changed driver profile supersedes old pending work instead of publishing new bytes under its old digest.

Deletion refuses files with entity attachments, then enqueues a durable purge. `corex:media:sweep` dispatches due work inside the host's trusted tenant job context. Purge releases original and conversion pins and retires their references atomically. It never deletes storage objects: Vaulter GC applies its grace period and preserves blobs still pinned by another reference.

Public failures are `CoreX\Media\Exceptions\MediaAccessDenied`, `MediaFileInUse` and `MediaOperationConflict`. Applications can handle access denial, attachment conflicts and retryable lifecycle conflicts without importing internals. Vaulter storage failures retain their original exception and roll back publication.

## Installation and configuration

Install `axiomasoft/corex-media`; it requires `axiomasoft/vaulter-core` and `axiomasoft/vaulter-media` and registers their providers. Bind `MediaPolicy` and configure Vaulter's private disk (`vaulter-media.disk`). The CoreX active connection and `vaulter.database.connection` must resolve to the **same Laravel Connection instance**; operations fail before I/O on a mismatch. Resolve services anew after switching tenant connections. A `TenantStorage` adapter is not used by this package.

Vaulter dedup scopes use the trusted account id. CoreX pins use `corex.media.file` with the media UUID and `corex.media.conversion` with the conversion id. Install both owners' schemas on the same tenant database. Never write Vaulter tables directly or interpret its opaque blob ids as paths.

## Schema installation

Install Vaulter's registered migrations using its documented schema lifecycle, then enable the CoreX media module through `Packman\Modules\Contracts\Lifecycle\ModuleLifecycle`. The CoreX set is `axiomasoft/corex-media:tenant`; Packman owns its migration journal.

This unreleased baseline uses `blob_id` in `med_files` and `med_conversions`, and records account ownership in `med_files`. It replaces the former private filesystem keys; there is no compatibility backend. Existing development databases must be recreated. Retention, legal holds, backup and storage-provider guarantees remain host responsibilities.
