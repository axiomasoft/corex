<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Jobs;

use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Media\Internal\ConversionCoordinator;
use CoreX\Media\Internal\MediaOperationStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packman\Attributes\Internal;

#[Internal]
final class ConvertMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $fileId, public readonly string $profileKey, public readonly string $operationKey) {}

    public function handle(MediaOperationStore $operations, StoredWorkRunner $runner, Application $app): void
    {
        $origin = $operations->conversionOrigin($this->fileId, $this->profileKey, $this->operationKey);

        if ($origin === null) {
            return;
        }

        [$scope, $proof] = $origin;
        $runner->runStored($scope, $proof, fn () => $app->make(ConversionCoordinator::class)->process(
            fileId: $this->fileId, profileKey: $this->profileKey, operationKey: $this->operationKey,
        ));
    }
}
