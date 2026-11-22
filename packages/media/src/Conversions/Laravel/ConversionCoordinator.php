<?php

declare(strict_types=1);

namespace CoreX\Media\Conversions\Laravel;

use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Media\Contracts\ConversionDriver;
use CoreX\Media\Conversions\Exceptions\UnsupportedConversionProfile;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use CoreX\Media\Data\MediaConversionRef;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Internal\ConversionProfiles;
use CoreX\Media\Internal\Data\ConversionClaim;
use CoreX\Media\Internal\Jobs\ConvertMedia;
use CoreX\Media\Internal\MediaBlobs;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Internal\Support\MediaOptions;
use CoreX\Media\Internal\Value;
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

    public function request(string $fileId, string $profileKey): MediaConversionRef
    {
        $this->blobs->assertConnection($this->connection);
        $driver = $this->profiles->driver($profileKey);

        if ($driver === null) {
            throw new UnsupportedConversionProfile('Unsupported media conversion profile.');
        }
        $profile = $driver->profile();
        $digest = $this->profiles->digest($driver);

        return $this->connection->transaction(function () use ($fileId, $profile, $digest): MediaConversionRef {
            $scope = $this->operations->currentConversionScope();
            $this->operations->enlist(...ReferenceWrites::targets(scope: $scope, producer: 'axiomasoft/corex-media', kind: 'conversion-profile', witness: ['profile' => $profile->key, 'digest' => $digest]));
            $identity = $this->operations->conversionIdentity($scope);
            $file = $this->connection->table('med_files')->where('id', $fileId)->where($identity)->lockForUpdate()->first();

            if ($file === null || $file->state !== 'ready') {
                throw new MediaOperationConflict('Conversion source unavailable.');
            }
            $ref = new MediaConversionRef(id: $this->operations->conversionKey(scope: $scope, fileId: $fileId, profileKey: $profile->key, profileDigest: $digest, sourceVersion: Value::integer($file->version)), fileId: $fileId, profile: $profile->key);
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

                if ($this->operations->conversionOrigin(fileId: $fileId, profileKey: $profile->key, operationKey: $ref->id) === null) {
                    throw new MediaOperationConflict('Conversion receipt belongs to an unavailable fence.');
                }

                return $ref;
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

            return $ref;
        });
    }

    private function enqueue(string $fileId, string $profileKey, string $profileDigest, int $sourceVersion): void
    {
        $key = $this->operations->enqueueConversion(fileId: $fileId, profileKey: $profileKey, profileDigest: $profileDigest, sourceVersion: $sourceVersion);
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
        $claim = $this->operations->claimConversion(fileId: $fileId, profileKey: $profileKey, profileDigest: $this->profiles->digest($driver), operationKey: $operationKey);

        if ($claim !== null) {
            $this->convert(claim: $claim, driver: $driver);
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

            if (strlen($sourceBytes) !== $claim->sourceSize || ! hash_equals($claim->sourceHash, hash('sha256', $sourceBytes))) {
                throw new MediaOperationConflict('Conversion source differs from the pinned file version.');
            }
            $output = $driver->convert(new ConversionInput(sourceBytes: $sourceBytes, sourceMimeType: $claim->sourceMimeType, profile: $driver->profile()));
            $this->assertOutputIsBounded(output: $output, profile: $driver->profile());
            $this->operations->completeConversion(claim: $claim, bytes: $output->bytes, mimeType: $output->mimeType, sizeBytes: strlen($output->bytes), width: $output->width, height: $output->height);
        } catch (Throwable $exception) {
            $this->operations->markConversionUnknown($claim);

            throw $exception;
        }
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
