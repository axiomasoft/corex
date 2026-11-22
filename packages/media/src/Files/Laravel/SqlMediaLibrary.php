<?php

declare(strict_types=1);

namespace CoreX\Media\Files\Laravel;

use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Conversions\Contracts\ConversionOperations;
use CoreX\Media\Conversions\Data\ConversionStatus;
use CoreX\Media\Conversions\Laravel\ConversionCoordinator;
use CoreX\Media\Data\DeleteResult;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaReadRef;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Exceptions\MediaAccessDenied;
use CoreX\Media\Exceptions\MediaFileInUse;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Media\Internal\Value;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlMediaLibrary implements ConversionOperations, MediaLibrary
{
    public function __construct(
        private ConnectionInterface $connection,
        private MediaBlobs $blobs,
        private MediaPolicy $policy,
        private MediaOperationStore $operations,
        private ConversionCoordinator $conversions,
        private MediaOptions $options,
        private ClockInterface $clock,
    ) {}

    public function store(MediaContext $context, UploadInput $input): MediaFileRef
    {
        $this->blobs->assertContext(connection: $this->connection, context: $context);

        if (! $this->policy->allows(context: $context, action: MediaAction::Store, subject: new MediaSubject)) {
            throw new MediaAccessDenied('Media upload is denied.');
        }

        $bytes = $input->file->bytes;
        $originalName = $input->file->originalName;

        if (! $input->file->valid
            || $bytes === null
            || $originalName === ''
            || str_contains($originalName, "\0")
            || str_contains($originalName, '/')
            || str_contains($originalName, '\\')
        ) {
            throw new MediaOperationConflict('Media upload is invalid.');
        }
        $checksum = hash('sha256', $bytes);

        if (! in_array($input->purpose, $this->options->purposes, true)) {
            throw new MediaOperationConflict('Media upload purpose is not registered.');
        }

        $digest = hash('sha256', $input->requestKey."\0".$checksum."\0".$input->purpose);
        $existing = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('request_key', $input->requestKey)->first();

        if ($existing !== null) {
            if ($existing->request_digest !== $digest || $existing->account_id !== $context->tenant->account->id || $existing->workspace_scope_key !== $context->tenant->workspace?->id) {
                throw new MediaOperationConflict('Media request key conflicts with different content.');
            }

            if ($existing->state !== 'ready') {
                throw new MediaOperationConflict('Media request is not ready for replay.');
            }

            return $this->reference($existing);
        }

        $id = (string) Str::uuid();
        $size = $input->file->size;

        if ($size < 1 || $size !== strlen($bytes) || $size > $this->options->maxUploadBytes) {
            throw new MediaOperationConflict('Media upload size is invalid.');
        }
        $mime = $input->file->mimeType ?? 'application/octet-stream';

        if (! in_array($mime, $this->options->allowedMimeTypes, true)) {
            throw new MediaOperationConflict('Media upload MIME type is invalid.');
        }

        $image = @getimagesizefromstring($bytes);

        if ($image !== false) {
            $maxImagePixels = $this->options->maxImagePixels;
            [$width, $height] = $image;

            if ($width < 1 || $height < 1 || $width > intdiv($maxImagePixels, $height)) {
                throw new MediaOperationConflict('Media image dimensions are invalid.');
            }
        }

        try {
            return $this->blobs->transaction(connection: $this->connection, callback: function () use ($context, $id, $input, $digest, $size, $mime, $checksum, $bytes): MediaFileRef {
                $scope = $this->operations->enlist();
                $this->connection->table('med_files')->insert([
                    ...$this->operations->conversionIdentity($scope),
                    'id' => $id, 'request_key' => $input->requestKey, 'request_digest' => $digest, 'state' => 'staged',
                    'blob_id' => null, 'mime_type' => $mime, 'size_bytes' => $size, 'sha256' => $checksum,
                    'kind' => $input->purpose, 'uploaded_actor_kind' => $context->actorKind,
                    'uploaded_actor_id' => $context->actorId, 'workspace_scope_key' => $context->tenant->workspace?->id,
                    'version' => 1, 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
                ]);
                // Owner row is locked before Vaulter acquires the blob lock.
                $blob = $this->blobs->commit(bytes: $bytes, accountId: $context->tenant->account->id);
                $reference = new MediaFileRef(id: $id, kind: $input->purpose, mimeType: $mime, sizeBytes: $size, sha256: $checksum, state: 'ready');

                if (! $this->policy->allows(context: $context, action: MediaAction::Store, subject: new MediaSubject($reference))) {
                    throw new MediaAccessDenied('Media upload is denied.');
                }
                $this->blobs->pin(blobId: $blob->id, ownerType: 'corex.media.file', referenceKey: $id);
                $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $id)->update([
                    'blob_id' => $blob->id, 'state' => 'ready', 'updated_at' => $this->clock->now(),
                ]);

                return $reference;
            });
        } catch (QueryException $exception) {
            $existing = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('request_key', $input->requestKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            if ($existing->request_digest !== $digest || $existing->state !== 'ready' || $existing->account_id !== $context->tenant->account->id || $existing->workspace_scope_key !== $context->tenant->workspace?->id) {
                throw new MediaOperationConflict('Media request key conflicts with different content or state.');
            }

            return $this->reference($existing);
        }
    }

    public function read(MediaContext $context, string $fileId): MediaReadRef
    {
        $this->blobs->assertContext(connection: $this->connection, context: $context);
        $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->where('state', 'ready')->first();

        if ($file === null || ! $this->policy->allows(context: $context, action: MediaAction::Read, subject: new MediaSubject($this->reference($file)))) {
            throw new MediaAccessDenied('Media read is denied.');
        }

        return new MediaReadRef(
            fileId: Value::text($file->id),
            mimeType: Value::text($file->mime_type),
            sizeBytes: Value::integer($file->size_bytes),
            sha256: Value::text($file->sha256),
            openStream: function () use ($context, $file): mixed {
                $this->blobs->assertContext(connection: $this->connection, context: $context);
                $current = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $file->id)->where('account_id', $context->tenant->account->id)->where('state', 'ready')->first();

                if ($current === null || ! $this->policy->allows(context: $context, action: MediaAction::Read, subject: new MediaSubject($this->reference($current)))) {
                    throw new MediaAccessDenied('Media read is denied.');
                }

                $stream = $this->blobs->read(Value::text($current->blob_id));

                if (! is_resource($stream)) {
                    throw new MediaOperationConflict('Media stream is unavailable.');
                }

                return $stream;
            },
        );
    }

    public function readConversion(MediaContext $context, string $fileId, string $profile): ?MediaReadRef
    {
        // The parent reference is the authorization boundary for every derivative.
        $this->read(context: $context, fileId: $fileId);
        $conversion = $this->conversions->ready(fileId: $fileId, profileKey: $profile);

        if ($conversion === null) {
            return null;
        }

        return new MediaReadRef(
            fileId: $fileId,
            mimeType: Value::text(Value::field(row: $conversion, name: 'mime_type')),
            sizeBytes: Value::integer(Value::field(row: $conversion, name: 'size_bytes')),
            sha256: Value::text(Value::field(row: $conversion, name: 'sha256')),
            openStream: function () use ($context, $fileId, $profile, $conversion): mixed {
                $this->read(context: $context, fileId: $fileId);
                $current = $this->conversions->ready(fileId: $fileId, profileKey: $profile);

                if ($current === null || Value::field(row: $current, name: 'id') !== Value::field(row: $conversion, name: 'id')) {
                    throw new MediaOperationConflict('The conversion reference is no longer current.');
                }

                return $this->blobs->read(Value::text(Value::field(row: $current, name: 'blob_id')));
            },
        );
    }

    public function delete(MediaContext $context, string $fileId): DeleteResult
    {
        $this->blobs->assertContext(connection: $this->connection, context: $context);
        $candidate = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->first();

        if ($candidate !== null && ! $this->policy->allows(context: $context, action: MediaAction::Delete, subject: new MediaSubject($this->reference($candidate)))) {
            throw new MediaAccessDenied('Media deletion is denied.');
        }
        $file = $this->connection->transaction(function () use ($context, $fileId): ?object {
            $this->operations->enlist();
            $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->lockForUpdate()->first();

            if ($file === null || in_array($file->state, ['deleted', 'deleting'], true)) {
                return null;
            }

            if (! $this->policy->allows(context: $context, action: MediaAction::Delete, subject: new MediaSubject($this->reference($file)))) {
                throw new MediaAccessDenied('Media deletion is denied.');
            }

            if ($this->connection->table('med_relations')->where('file_id', $fileId)->exists()) {
                throw new MediaFileInUse('Media file still has usages.');
            }
            $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->update(['state' => 'deleting', 'updated_at' => $this->clock->now()]);
            $this->operations->enqueuePurge(fileId: $fileId);

            return $file;
        });

        if ($file === null) {
            return new DeleteResult(deleted: true, purgePending: false);
        }

        return new DeleteResult(deleted: true, purgePending: true);
    }

    public function requestConversion(MediaContext $context, string $fileId, string $profile): MediaConversionRef
    {
        $this->blobs->assertContext(connection: $this->connection, context: $context);
        $file = $this->connection->table('med_files')->where($this->operations->conversionIdentity($this->operations->currentConversionScope()))->where('id', $fileId)->where('account_id', $context->tenant->account->id)->where('state', 'ready')->first();

        if ($file === null || ! $this->policy->allows(context: $context, action: MediaAction::Read, subject: new MediaSubject($this->reference($file)))) {
            throw new MediaAccessDenied('Media conversion is denied.');
        }

        return $this->conversions->request(fileId: $fileId, profileKey: $profile);
    }

    public function inspect(MediaContext $context, MediaConversionRef $ref): ConversionStatus
    {
        $this->read(context: $context, fileId: $ref->fileId);

        return $this->operations->status($ref);
    }

    private function reference(object $file): MediaFileRef
    {
        return new MediaFileRef(id: Value::text(Value::field(row: $file, name: 'id')), kind: Value::text(Value::field(row: $file, name: 'kind')), mimeType: Value::text(Value::field(row: $file, name: 'mime_type')), sizeBytes: Value::integer(Value::field(row: $file, name: 'size_bytes')), sha256: Value::text(Value::field(row: $file, name: 'sha256')), state: Value::text(Value::field(row: $file, name: 'state')));
    }
}
