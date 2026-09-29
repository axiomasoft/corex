<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Data\DataScope;
use CoreX\Search\Data\DocumentBatch;
use CoreX\Search\Data\DocumentCursor;

interface SearchDocuments
{
    public function page(DataScope $scope, DocumentCursor $after, int $limit): DocumentBatch;
}
