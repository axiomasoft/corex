<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Enums;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
}
