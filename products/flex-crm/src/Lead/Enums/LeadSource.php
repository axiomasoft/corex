<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Enums;

use Packman\Attributes\Internal;

#[Internal]
enum LeadSource: string
{
    case Website = 'website';
    case Referral = 'referral';
    case Ads = 'ads';
    case ColdCall = 'cold_call';
    case Event = 'event';
    case Import = 'import';
    case Other = 'other';
}
