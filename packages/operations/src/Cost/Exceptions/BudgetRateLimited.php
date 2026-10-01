<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetRateLimited extends TooManyRequestsHttpException {}
