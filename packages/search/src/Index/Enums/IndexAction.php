<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum IndexAction: string
{
    case Create = 'create';
    case Upsert = 'upsert';
    case DeleteRecords = 'deleterecords';
    case Drop = 'drop';
}
