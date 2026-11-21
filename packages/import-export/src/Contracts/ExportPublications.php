<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Data\Export\ExportManifest;
use CoreX\ImportExport\Data\TransferRef;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface ExportPublications
{
    public function publish(DataScope $scope, TransferRef $transfer, ExportManifest $manifest): void;
}
