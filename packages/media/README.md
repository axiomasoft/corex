# axiomasoft/corex-media

Private media references, attachment policy and conversions for CoreX, backed by Vaulter's content-addressed storage. `MediaPolicy` denies every action until the host explicitly permits it. The package exposes no public storage URLs.

`CoreX\Media\Files\Contracts\MediaLibrary` stores and reads files. `CoreX\Media\Attachments\Contracts\MediaAttachments` records opaque attachment edges to application entities. Attach and detach require write access to the related record. `usages()` returns only edges the current caller is authorized to see. CoreX owns purpose, authorization, entity relations and conversion scheduling. Vaulter owns the bytes, deduplication, pins and physical garbage collection.

```php
$files = app(\CoreX\Media\Files\Contracts\MediaLibrary::class);
$file = $files->store(
    context: $trustedMediaContext,
    input: new \CoreX\Media\Data\UploadInput(
        requestKey: $idempotencyKey,
        file: \CoreX\Media\Laravel\UploadedFileSource::fromFile($uploadedFile),
        purpose: 'attachment',
    ),
);

app(\CoreX\Media\Attachments\Contracts\MediaAttachments::class)->attach(
    context: $trustedMediaContext,
    input: new \CoreX\Media\Data\AttachmentInput(
        fileId: $file->id,
        entityHandle: $entityHandle,
        entityId: $entityId,
        slot: 'attachment',
    ),
);

$conversion = $files->requestConversion($trustedMediaContext, $file->id, 'thumbnail');
$status = app(\CoreX\Media\Conversions\Contracts\ConversionOperations::class)
    ->inspect($trustedMediaContext, $conversion);
```

An upload inserts its owner reference, commits the bytes through `BlobStore` and pins the blob in one `VaulterDatabase` transaction. Retrying the same request returns the same media reference; distinct references to identical bytes have independent pins. Publication or pin failures roll back the owner row. Unreferenced physical objects are Vaulter's cleanup responsibility.

With PHP `ext-gd`, the bounded `thumbnail` profile handles PNG and JPEG. Hosts can also register `ConversionDriver` implementations. `requestConversion()` records a durable intent for the current scope, source version and driver profile digest. Repeating that request returns the same `MediaConversionRef`. Use `inspect()` with the current authorized context to check whether the operation is pending, processing, ready, failed or unknown. A ready status supplies the confirmed output receipt. An unknown status requires reconciliation.

Conversions use the same byte and pin protocol as uploads after source-version and worker-lease checks. Output becomes visible after the blob pin and reference commit together. `readConversion()` returns an authorized `MediaReadRef` when the current profile has published output and null otherwise. Its `openStream` closure rechecks the current tenant, parent policy and profile before reading bytes. An expired worker claim can be reclaimed with a new epoch. Stale completions cannot publish, and a changed driver profile supersedes old pending work.

Deletion refuses files with entity attachments, then enqueues a durable purge. `corex:media:sweep` dispatches due work inside the host's trusted tenant job context. Purge releases original and conversion pins and retires their references atomically. It never deletes storage objects: Vaulter GC applies its grace period and preserves blobs still pinned by another reference.

Public failures are `CoreX\Media\Exceptions\MediaAccessDenied`, `MediaFileInUse` and `MediaOperationConflict`. Applications can handle access denial, attachment conflicts and retryable lifecycle conflicts without importing internals. Vaulter storage failures retain their original exception and roll back publication.

## Installation and configuration

Install `axiomasoft/corex-media`; it requires `axiomasoft/vaulter-core` and `axiomasoft/vaulter-media` and registers their providers. Bind `MediaPolicy` and configure Vaulter's private disk (`vaulter-media.disk`). The CoreX active connection and `vaulter.database.connection` must resolve to the **same Laravel Connection instance**; operations fail before I/O on a mismatch. Resolve services anew after switching tenant connections. A `TenantStorage` adapter is not used by this package.

Vaulter dedup scopes use the trusted account id. CoreX pins use `corex.media.file` with the media UUID and `corex.media.conversion` with the conversion id. Install both owners' schemas on the same tenant database. Never write Vaulter tables directly or interpret its opaque blob ids as paths.

## Schema installation

Install Vaulter's registered `axiomasoft/vaulter-core:core` and `axiomasoft/vaulter-media:media` sets on the configured Vaulter connection using Vaulter's schema tooling. The Media capability declares `axiomasoft/corex-media` as a Packman module and owns the version 3 tenant set `axiomasoft/corex-media:tenant`. Enable it through `Packman\Modules\Contracts\Lifecycle\ModuleLifecycle` in the verified tenant context with the required operator grant. Packman owns the Media migration journal.

This unreleased baseline uses `blob_id` in `med_files` and `med_conversions`, and records account ownership in `med_files`. It replaces the former private filesystem keys; there is no compatibility backend. Existing development databases must be recreated. Retention, legal holds, backup and storage-provider guarantees remain host responsibilities.
