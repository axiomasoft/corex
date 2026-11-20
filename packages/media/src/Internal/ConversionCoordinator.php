<?php

declare(strict_types=1);

namespace CoreX\Media\Internal;

use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Internal\Data\ConversionClaim;
use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\Support\MediaOptions;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use Throwable;

#[Internal]
final readonly class ConversionCoordinator
{
    public function __construct(
        private Connection $connection,
        private MediaBlobs $blobs,
        private ConversionProfiles $profiles,
        private MediaOperationStore $operations,
        private MediaOptions $options,
        private ClockInterface $clock,
        private ExceptionHandler $exceptions,
    ) {}

    public function request(string $fileId, string $profileKey): ?MediaConversionRef
    {
        $this->blobs->assertConnection($this->connection);
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            return null;
        }
        $profile = $driver->profile();
        $digest = $this->profiles->digest($driver);

        return $this->connection->transaction(function () use ($fileId, $profile, $digest): ?MediaConversionRef {
            $scope = $this->operations->currentConversionScope();
            $identity = $this->operations->conversionIdentity($scope);
            $file = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $scope->accountId)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                return null;
            }
            $conversion = $this->connection->table('med_conversions')->where([
                ...$identity,
                'file_id' => $fileId,
                'profile_key' => $profile->key,
                'profile_digest' => $digest,
                'source_version' => $file->version,
            ])->lockForUpdate()->first();

            if ($conversion !== null) {
                if ($conversion->state === 'superseded') {
                    // A deployment rollback may reactivate a previously retired digest.
                    $this->connection->table('med_conversions')->where('id', $conversion->id)->update(['state' => 'pending', 'updated_at' => $this->clock->now()]);
                    $this->enqueue(fileId: $fileId, profileKey: $profile->key, profileDigest: $digest, sourceVersion: Value::integer($file->version));
                }

                return $conversion->state === 'ready' ? $this->reference($conversion) : null;
            }

            $this->connection->table('med_conversions')->insert([
                ...$identity,
                'file_id' => $fileId,
                'profile_key' => $profile->key,
                'profile_digest' => $digest,
                'source_version' => $file->version,
                'state' => 'pending',
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->enqueue(fileId: $fileId, profileKey: $profile->key, profileDigest: $digest, sourceVersion: Value::integer($file->version));

            return null;
        });
    }

    private function enqueue(string $fileId, string $profileKey, string $profileDigest, int $sourceVersion): void
    {
        $key = $this->operations->enqueueConversion($fileId, $profileKey, $profileDigest, $sourceVersion);
        $this->connection->afterCommit(function () use ($fileId, $profileKey, $key): void {
            try {
                ConvertMedia::dispatch($fileId, $profileKey, $key);
            } catch (Throwable $exception) {
                $this->exceptions->report($exception);
            }
        });
    }

    public function ready(string $fileId, string $profileKey): ?object
    {
        $this->blobs->assertConnection($this->connection);
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            return null;
        }
        $file = $this->connection->table('med_files')->where('id', $fileId)->where('account_id', $this->blobs->accountId())->where('state', 'ready')->first();

        if ($file === null) {
            return null;
        }

        $identity = $this->operations->conversionIdentity($this->operations->currentConversionScope());

        return $this->connection->table('med_conversions')->where($identity)->where('file_id', $fileId)
            ->where('profile_key', $profileKey)->where('profile_digest', $this->profiles->digest($driver))
            ->where('source_version', $file->version)->where('state', 'ready')->first();
    }

    public function process(string $fileId, string $profileKey, string $operationKey): void
    {
        $this->blobs->assertConnection($this->connection);
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            return;
        }
        $claim = $this->operations->claimConversion($fileId, $profileKey, $this->profiles->digest($driver), $operationKey);

        if ($claim !== null) {
            $this->convert($claim, $driver);
        }
    }

    private function convert(ConversionClaim $claim, ConversionDriver $driver): void
    {
        try {
            $stream = $this->blobs->read($claim->sourceBlobId);

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
            $this->operations->completeConversion(claim: $claim, bytes: $output->bytes, mimeType: $output->mimeType, sizeBytes: strlen($output->bytes), width: $output->width, height: $output->height);
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
