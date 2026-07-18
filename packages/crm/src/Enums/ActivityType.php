<?php

declare(strict_types=1);

namespace Flex\Crm\Enums;

enum ActivityType: string
{
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case Note = 'note';
    case Task = 'task';
}
