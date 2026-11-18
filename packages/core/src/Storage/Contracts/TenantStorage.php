<?php

declare(strict_types=1);

namespace CoreX\Storage\Contracts;

use CoreX\Storage\Data\ExportArchiveRef;
use CoreX\Storage\Data\StorageCapabilities;
use Illuminate\Contracts\Filesystem\Filesystem;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantStorage
{
    public function disk(): Filesystem;

    public function ephemeralDisk(): Filesystem;

    public function capabilities(): StorageCapabilities;

    public function usageBytes(): int;

    public function exportArchive(): ExportArchiveRef;
}
