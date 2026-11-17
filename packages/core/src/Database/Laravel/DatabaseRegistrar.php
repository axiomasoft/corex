<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Internal\CoreSchemaSets;
use Illuminate\Contracts\Foundation\Application;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

/** Registers CoreX-owned schema; Packman owns execution and module claims. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DatabaseRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    public function register(Application $app): void
    {
        $app->tag([CoreSchemaSets::class], SchemaSetProvider::class);
    }
}
