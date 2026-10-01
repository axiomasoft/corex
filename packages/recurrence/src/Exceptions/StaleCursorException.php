<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Exceptions;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final class StaleCursorException extends InvalidArgumentException {}
