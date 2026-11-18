<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Enums;

use Packman\Attributes\Internal;

#[Internal]
enum LeadStatus: string
{
    case Open = 'open';
    case Junk = 'junk';
    case Converted = 'converted';
}
