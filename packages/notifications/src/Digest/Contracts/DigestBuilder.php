<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Contracts;

use CoreX\Notifications\Digest\Data\DigestRef;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DigestBuilder
{
    public function build(NotificationContext $context, DigestWindow $window): ?DigestRef;
}
