<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\DocumentBatch;
use CoreX\Search\Data\DocumentCursor;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchDocuments
{
    public function page(DataScope $scope, DocumentCursor $after, int $limit): DocumentBatch;
}
