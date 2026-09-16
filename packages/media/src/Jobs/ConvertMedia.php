<?php

declare(strict_types=1);

namespace CoreX\Media\Jobs;

use CoreX\Media\Internal\ConversionCoordinator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ConvertMedia implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $fileId, public readonly string $profileKey) {}

    public function handle(ConversionCoordinator $coordinator): void
    {
        $coordinator->process(fileId: $this->fileId, profileKey: $this->profileKey);
    }
}
