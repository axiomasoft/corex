<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Invalidation\Contracts;

use CoreX\Messaging\Enums\BrokerReceipt;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldSearchSubscriber
{
    public const string CONSUMER = 'search.custom-fields';

    public function consume(string $restrictedRef): BrokerReceipt;
}
