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

final class ConversionCoordinator
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly TenantStorage $storage,
        private readonly ConversionProfiles $profiles,
        private readonly MediaOperationStore $operations,
        private readonly MediaOptions $options,
        private readonly ClockInterface $clock,
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
        $claim = $this->operations->claimConversion($fileId, $profileKey);

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

            $output = $driver->convert(new ConversionInput($sourceBytes, $claim->sourceMimeType, $driver->profile()));
            $this->assertOutputIsBounded($output, $driver->profile());
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

            if (! $this->operations->completeConversion($claim, $output->mimeType, strlen($output->bytes), $output->width, $output->height)) {
                $this->storage->disk()->delete($claim->attemptObjectKey);
            }
        } catch (Throwable $exception) {
            $this->operations->markConversionUnknown($claim);

            throw $exception;
        }
    }

    private function reference(object $conversion): MediaConversionRef
    {
        return new MediaConversionRef(Value::integer(Value::field($conversion, 'id')), Value::text(Value::field($conversion, 'file_id')), Value::text(Value::field($conversion, 'profile_key')), Value::text(Value::field($conversion, 'mime_type')), Value::integer(Value::field($conversion, 'size_bytes')), Value::integer(Value::field($conversion, 'width')), Value::integer(Value::field($conversion, 'height')));
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
