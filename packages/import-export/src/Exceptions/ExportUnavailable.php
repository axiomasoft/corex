<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Exceptions;

use Packman\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class ExportUnavailable extends RuntimeException {}
