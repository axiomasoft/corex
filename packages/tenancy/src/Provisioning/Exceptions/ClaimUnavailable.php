<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class ClaimUnavailable extends RuntimeException {}
