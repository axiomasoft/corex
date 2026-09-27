<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\DatasetManifest;
use CoreX\Intl\Data\GeographyQuery;
use CoreX\Intl\Data\ReferenceCursor;
use CoreX\Intl\Data\ReferencePage;
use CoreX\Support\Data\DataScope;

interface GeographyProvider
{
    public function describe(DataScope $scope): DatasetManifest;

    public function query(DataScope $scope, GeographyQuery $query, ?ReferenceCursor $after = null): ReferencePage;
}
