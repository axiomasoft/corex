<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** @internal Identifies a reserve denial without conflating handler-originated 429s. */
final class BudgetRateLimited extends TooManyRequestsHttpException {}
