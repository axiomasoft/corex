<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class DomainTrustDisabled extends RuntimeException {}
