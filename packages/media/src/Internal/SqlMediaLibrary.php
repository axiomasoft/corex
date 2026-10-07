<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\DeleteResult;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Data\MediaFileRef;
use CoreX\Media\Data\MediaReadRef;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use Throwable;

final class SqlMediaLibrary implements MediaLibrary
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TenantStorage $storage,
        private readonly MediaPolicy $policy,
        private readonly MediaOperationStore $operations,
        private readonly ConversionCoordinator $conversions,
        private readonly MediaOptions $options,
        private readonly ClockInterface $clock,
    ) {}

    public function store(MediaContext $context, UploadInput $input): MediaFileRef
    {
        if (! $this->policy->allows($context, MediaAction::Store, new MediaSubject)) {
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
        $existing = $this->connection->table('med_files')->where('request_key', $input->requestKey)->first();

        if ($existing !== null) {
            if ($existing->request_digest !== $digest) {
                throw new MediaOperationConflict('Media request key conflicts with different content.');
            }

            if ($existing->state !== 'ready') {
                throw new MediaOperationConflict('Media request is not ready for replay.');
            }

            return $this->reference($existing);
        }

        $id = (string) Str::uuid();
        $key = "media/originals/{$id}";
        $size = $input->file->size;

        if ($size < 1 || $size > $this->options->maxUploadBytes) {
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
            $this->connection->transaction(function () use ($context, $id, $input, $digest, $key, $size, $mime, $checksum): void {
                $this->connection->table('med_files')->insert([
                    'id' => $id, 'request_key' => $input->requestKey, 'request_digest' => $digest, 'state' => 'staged',
                    'object_key' => $key, 'mime_type' => $mime, 'size_bytes' => $size, 'sha256' => $checksum,
                    'kind' => $input->purpose, 'uploaded_actor_kind' => $context->actorKind,
                    'uploaded_actor_id' => $context->actorId, 'workspace_scope_key' => $context->tenant->workspace?->id,
                    'version' => 1, 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
                ]);
            });
        } catch (QueryException $exception) {
            $existing = $this->connection->table('med_files')->where('request_key', $input->requestKey)->first();

            if ($existing === null) {
                throw $exception;
            }

            if ($existing->request_digest !== $digest) {
                throw new MediaOperationConflict('Media request key conflicts with different content.');
            }

            if ($existing->state !== 'ready') {
                throw new MediaOperationConflict('Media request is not ready for replay.');
            }

            return $this->reference($existing);
        }

        $stream = fopen('php://temp', 'w+b');

        if ($stream !== false) {
            fwrite($stream, $bytes);
            rewind($stream);
        }

        try {
            if ($stream === false || ! $this->storage->disk()->writeStream($key, $stream, ['visibility' => 'private'])) {
                throw new MediaOperationConflict('Media bytes could not be stored.');
            }
        } catch (Throwable $exception) {
            $this->connection->transaction(function () use ($id): void {
                $this->connection->table('med_files')->where('id', $id)->where('state', 'staged')->update(['state' => 'deleting', 'updated_at' => $this->clock->now()]);
                $this->operations->enqueuePurge(fileId: $id);
            });

            throw $exception instanceof MediaOperationConflict
                ? $exception
                : new MediaOperationConflict('Media bytes could not be stored.', previous: $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $published = $this->connection->transaction(function () use ($context, $id): ?bool {
            $file = $this->connection->table('med_files')->where('id', $id)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'staged') {
                return false;
            }

            if (! $this->policy->allows($context, MediaAction::Store, new MediaSubject($this->reference($file)))) {
                $this->connection->table('med_files')->where('id', $id)->update(['state' => 'deleting', 'updated_at' => $this->clock->now()]);
                $this->operations->enqueuePurge(fileId: $id);

                return null;
            }

            return $this->connection->table('med_files')->where('id', $id)->where('state', 'staged')->update(['state' => 'ready', 'updated_at' => $this->clock->now()]) === 1;
        });

        if ($published === null) {
            throw new MediaAccessDenied('Media upload is denied.');
        }

        if (! $published) {
            throw new MediaOperationConflict('Media upload could not be published.');
        }

        return new MediaFileRef($id, $input->purpose, $mime, $size, $checksum, 'ready');
    }

    public function read(MediaContext $context, string $fileId): MediaReadRef
    {
        $file = $this->connection->table('med_files')->where('id', $fileId)->where('state', 'ready')->first();

        if ($file === null || ! $this->policy->allows($context, MediaAction::Read, new MediaSubject($this->reference($file)))) {
            throw new MediaAccessDenied('Media read is denied.');
        }

        return new MediaReadRef(
            Value::text($file->id),
            Value::text($file->mime_type),
            Value::integer($file->size_bytes),
            Value::text($file->sha256),
            function () use ($context, $file): mixed {
                $current = $this->connection->table('med_files')->where('id', $file->id)->where('state', 'ready')->first();

                if ($current === null || ! $this->policy->allows($context, MediaAction::Read, new MediaSubject($this->reference($current)))) {
                    throw new MediaAccessDenied('Media read is denied.');
                }

                $stream = $this->storage->disk()->readStream(Value::text($current->object_key));

                if (! is_resource($stream)) {
                    throw new MediaOperationConflict('Media stream is unavailable.');
                }

                return $stream;
            },
        );
    }

    public function delete(MediaContext $context, string $fileId): DeleteResult
    {
        $candidate = $this->connection->table('med_files')->where('id', $fileId)->first();

        if ($candidate !== null && ! $this->policy->allows($context, MediaAction::Delete, new MediaSubject($this->reference($candidate)))) {
            throw new MediaAccessDenied('Media deletion is denied.');
        }
        $file = $this->connection->transaction(function () use ($context, $fileId): ?object {
            $file = $this->connection->table('med_files')->where('id', $fileId)->lockForUpdate()->first();

            if ($file === null || in_array($file->state, ['deleted', 'deleting'], true)) {
                return null;
            }

            if (! $this->policy->allows($context, MediaAction::Delete, new MediaSubject($this->reference($file)))) {
                throw new MediaAccessDenied('Media deletion is denied.');
            }

            if ($this->connection->table('med_relations')->where('file_id', $fileId)->exists()) {
                throw new MediaFileInUse('Media file still has usages.');
            }
            $this->connection->table('med_files')->where('id', $fileId)->update(['state' => 'deleting', 'updated_at' => $this->clock->now()]);
            $this->operations->enqueuePurge(fileId: $fileId);

            return $file;
        });

        if ($file === null) {
            return new DeleteResult(true, false);
        }

        return new DeleteResult(true, true);
    }

    public function conversion(MediaContext $context, string $fileId, string $profile): ?MediaConversionRef
    {
        $file = $this->connection->table('med_files')->where('id', $fileId)->where('state', 'ready')->first();

        if ($file === null || ! $this->policy->allows($context, MediaAction::Read, new MediaSubject($this->reference($file)))) {
            throw new MediaAccessDenied('Media conversion is denied.');
        }

        return $this->conversions->request($fileId, $profile);
    }

    private function reference(object $file): MediaFileRef
    {
        return new MediaFileRef(Value::text(Value::field($file, 'id')), Value::text(Value::field($file, 'kind')), Value::text(Value::field($file, 'mime_type')), Value::integer(Value::field($file, 'size_bytes')), Value::text(Value::field($file, 'sha256')), Value::text(Value::field($file, 'state')));
    }
}
