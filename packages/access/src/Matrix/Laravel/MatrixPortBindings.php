<?php

declare(strict_types=1);

namespace CoreX\Access\Matrix\Laravel;

use CoreX\Access\Matrix\Contracts\EntityActivity;
use CoreX\Access\Matrix\Contracts\ScopeMapVersions;
use CoreX\Access\Matrix\Internal\ActiveEntityFilter;
use CoreX\Access\Matrix\Internal\ScopeMapVersion;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MatrixPortBindings
{
    public static function register(Application $app): void
    {
        $app->bindIf(EntityActivity::class, static fn (Application $app): EntityActivity => $app->make(ActiveEntityFilter::class));
        $app->bind(ScopeMapVersions::class, static fn (Application $app): ScopeMapVersions => $app->make(ScopeMapVersion::class));
    }
}
