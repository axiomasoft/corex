<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Attributes\Api;
use DomainException;

#[Api(since: '1.0')]
final class CentralUnauthorized extends DomainException {}
