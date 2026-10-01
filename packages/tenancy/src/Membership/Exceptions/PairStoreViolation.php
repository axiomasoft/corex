<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use CoreX\Attributes\Api;
use LogicException;

#[Api(since: '1.0')]
final class PairStoreViolation extends LogicException {}
