<?php

declare(strict_types=1);

namespace CoreX\Transfer;

use Illuminate\Support\ServiceProvider;
use Override;

final class TransferServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-transfer.php', 'corex-transfer');
    }
}
