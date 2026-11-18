<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Intl\Territory\Data\DatasetManifest;
use CoreX\Intl\Territory\Data\GeographyQuery;
use CoreX\Intl\Territory\Data\ReferenceCursor;
use CoreX\Intl\Territory\Data\ReferencePage;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface GeographyProvider
{
    public function describe(DataScope $scope): DatasetManifest;

    public function query(DataScope $scope, GeographyQuery $query, ?ReferenceCursor $after = null): ReferencePage;
}
