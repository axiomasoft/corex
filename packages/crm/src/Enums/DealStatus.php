<?php

declare(strict_types=1);

namespace CoreX\Crm\Enums;

enum DealStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
}
