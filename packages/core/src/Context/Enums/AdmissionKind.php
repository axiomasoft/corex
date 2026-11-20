<?php

declare(strict_types=1);

namespace CoreX\Context\Enums;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
enum AdmissionKind: string
{
    case ReceiptProjection = 'receipt-projection';
    case LocalWrite = 'local-write';
    case ProviderSend = 'provider-send';
}
