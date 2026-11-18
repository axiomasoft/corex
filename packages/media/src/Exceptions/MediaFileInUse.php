<?php

declare(strict_types=1);

namespace CoreX\Media\Exceptions;

use Packman\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class MediaFileInUse extends RuntimeException {}
