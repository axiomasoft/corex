<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum MeilisearchAccess: string
{
    case Search = 'search';
    case Index = 'index';
    case Observe = 'observe';
}
