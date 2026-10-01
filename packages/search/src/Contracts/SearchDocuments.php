<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Data\DocumentBatch;
use CoreX\Search\Data\DocumentCursor;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SearchDocuments
{
    public function page(DataScope $scope, DocumentCursor $after, int $limit): DocumentBatch;
}
