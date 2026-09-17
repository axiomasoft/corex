<?php

declare(strict_types=1);

namespace CoreX\Search\Contracts;

use CoreX\Search\Support\DocumentBatch;
use CoreX\Search\Support\DocumentCursor;
use CoreX\Support\Data\DataScope;

interface SearchDocuments
{
    public function page(DataScope $scope, DocumentCursor $after, int $limit): DocumentBatch;
}
