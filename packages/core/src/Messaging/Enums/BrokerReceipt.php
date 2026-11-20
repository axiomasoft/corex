<?php

declare(strict_types=1);

namespace CoreX\Messaging\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum BrokerReceipt: string
{
    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Quarantined = 'quarantined';
}
