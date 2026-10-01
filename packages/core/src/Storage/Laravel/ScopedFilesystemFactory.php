<?php

declare(strict_types=1);

namespace CoreX\Storage\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Storage\Internal\ScopedFilesystem;
use Illuminate\Contracts\Filesystem\Filesystem;

/** Builds the filesystem view pinned to the captured account and storage root. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ScopedFilesystemFactory
{
    public static function create(
        Filesystem $filesystem,
        TenantContextResolver $contexts,
        string $accountId,
        string $root,
        bool $localPaths = true,
    ): Filesystem {
        return new ScopedFilesystem($filesystem, $contexts, $accountId, $root, $localPaths);
    }
}
