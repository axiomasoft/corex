<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationOperationConflict extends RuntimeException {}
