<?php

declare(strict_types=1);

namespace CoreX\Media\Conversions\Exceptions;

use Packman\Attributes\Api;
use RuntimeException;

#[Api(since: '2.0.0')]
final class UnsupportedConversionProfile extends RuntimeException {}
