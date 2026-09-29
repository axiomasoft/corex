<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Jobs;

use CoreX\Media\Internal\MediaOperationConflict;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class PurgeMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $fileId) {}

    public function handle(MediaOperationStore $operations, TenantStorage $storage): void
    {
        $claim = $operations->claimPurge($this->fileId);

        if ($claim === null) {
            return;
        }

        try {
            foreach ($claim->objectKeys as $objectKey) {
                if (! $storage->disk()->delete($objectKey)) {
                    throw new MediaOperationConflict('Media bytes could not be purged.');
                }
            }
            $operations->completePurge($claim);
        } catch (Throwable $exception) {
            $operations->markUnknown($claim);

            throw $exception;
        }
    }
}
