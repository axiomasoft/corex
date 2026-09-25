<?php

declare(strict_types=1);

namespace CoreX\Api\Enums;

enum OperationMode: string
{
    case Query = 'query';
    case Command = 'command';
    case AsyncCommand = 'async_command';
    case BulkQuery = 'bulk_query';
    case BulkCommand = 'bulk_command';
}
