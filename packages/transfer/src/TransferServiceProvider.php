<?php

declare(strict_types=1);

namespace CoreX\Transfer;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Transfer\Internal\TransferOptions;
use CoreX\Transfer\Internal\TransferSchemaSets;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TransferServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([TransferSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-transfer.php', 'corex-transfer');
        $this->app->singleton(TransferOptions::class, static fn (Application $app): TransferOptions => TransferOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-transfer', []),
        ));
    }

    public function boot(): void
    {
        $this->app->make(TransferOptions::class);
    }
}
