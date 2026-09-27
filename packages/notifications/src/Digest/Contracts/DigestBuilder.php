<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\DigestRef;
use CoreX\Notifications\Data\DigestWindow;
use CoreX\Notifications\Data\NotificationContext;

interface DigestBuilder
{
    public function build(NotificationContext $context, DigestWindow $window): ?DigestRef;
}
