<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;
use Throwable;

final readonly class ConversionCoordinator
{
    public function __construct(
        private ConnectionInterface $connection,
        private TenantStorage $storage,
        private ConversionProfiles $profiles,
        private MediaOperationStore $operations,
        private MediaOptions $options,
        private ClockInterface $clock,
    ) {}

    public function request(string $fileId, string $profileKey): ?MediaConversionRef
    {
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            return null;
        }
        $profile = $driver->profile();
        $digest = $this->profiles->digest($driver);

        return $this->connection->transaction(function () use ($fileId, $profile, $digest): ?MediaConversionRef {
            $file = $this->connection->table('med_files')->where('id', $fileId)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                return null;
            }
            $conversion = $this->connection->table('med_conversions')->where([
                'file_id' => $fileId,
                'profile_key' => $profile->key,
                'profile_digest' => $digest,
                'source_version' => $file->version,
            ])->lockForUpdate()->first();

            if ($conversion !== null) {
                return $conversion->state === 'ready' ? $this->reference($conversion) : null;
            }

            $this->connection->table('med_conversions')->insert([
                'file_id' => $fileId,
                'profile_key' => $profile->key,
                'profile_digest' => $digest,
                'source_version' => $file->version,
                'state' => 'pending',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->operations->enqueueConversion(fileId: $fileId, profileKey: $profile->key, profileDigest: $digest, sourceVersion: Value::integer($file->version));

            return null;
        });
    }

    public function process(string $fileId, string $profileKey): void
    {
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            return;
        }
        $claim = $this->operations->claimConversion(fileId: $fileId, profileKey: $profileKey);

        if ($claim === null) {
            return;
        }

        try {
            $stream = $this->storage->disk()->readStream($claim->sourceKey);

            if (! is_resource($stream)) {
                throw new MediaOperationConflict('The conversion source could not be opened.');
            }

            try {
                $sourceBytes = stream_get_contents($stream, $this->options->maxUploadBytes + 1);
            } finally {
                fclose($stream);
            }

            if (! is_string($sourceBytes) || $sourceBytes === '' || strlen($sourceBytes) > $this->options->maxUploadBytes) {
                throw new MediaOperationConflict('The conversion source exceeds byte limits.');
            }

            $output = $driver->convert(new ConversionInput(sourceBytes: $sourceBytes, sourceMimeType: $claim->sourceMimeType, profile: $driver->profile()));
            $this->assertOutputIsBounded(output: $output, profile: $driver->profile());
            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new MediaOperationConflict('The conversion output stream could not be opened.');
            }

            try {
                fwrite($stream, $output->bytes);
                rewind($stream);

                if (! $this->storage->disk()->writeStream($claim->attemptObjectKey, $stream, ['visibility' => 'private'])) {
                    throw new MediaOperationConflict('The conversion output could not be stored.');
                }
            } finally {
                fclose($stream);
            }

            if (! $this->operations->completeConversion(claim: $claim, mimeType: $output->mimeType, sizeBytes: strlen($output->bytes), width: $output->width, height: $output->height)) {
                $this->storage->disk()->delete($claim->attemptObjectKey);
            }
        } catch (Throwable $exception) {
            $this->operations->markConversionUnknown($claim);

            throw $exception;
        }
    }

    private function reference(object $conversion): MediaConversionRef
    {
        return new MediaConversionRef(id: Value::integer(Value::field(row: $conversion, name: 'id')), fileId: Value::text(Value::field(row: $conversion, name: 'file_id')), profile: Value::text(Value::field(row: $conversion, name: 'profile_key')), mimeType: Value::text(Value::field(row: $conversion, name: 'mime_type')), sizeBytes: Value::integer(Value::field(row: $conversion, name: 'size_bytes')), width: Value::integer(Value::field(row: $conversion, name: 'width')), height: Value::integer(Value::field(row: $conversion, name: 'height')));
    }

    private function assertOutputIsBounded(ConversionOutput $output, ConversionProfile $profile): void
    {
        if (strlen($output->bytes) > $profile->maxOutputBytes || $output->width > $profile->maxWidth || $output->height > $profile->maxHeight || $output->width * $output->height > $profile->maxPixels) {
            throw new MediaOperationConflict('The conversion output exceeds profile limits.');
        }

        if (! in_array($output->mimeType, ['image/jpeg', 'image/png'], true)) {
            return;
        }

        $facts = getimagesizefromstring($output->bytes);

        if ($facts === false || $facts['mime'] !== $output->mimeType || $facts[0] !== $output->width || $facts[1] !== $output->height) {
            throw new MediaOperationConflict('The conversion output facts are invalid.');
        }
    }
}
