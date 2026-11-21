<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Jobs;

use CoreX\Media\Internal\MediaOperationStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packman\Attributes\Internal;

#[Internal]
final class PurgeMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $fileId) {}

    public function handle(MediaOperationStore $operations): void
    {
        $operations->purge($this->fileId);
    }
}
