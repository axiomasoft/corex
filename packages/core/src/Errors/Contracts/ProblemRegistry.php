<?php

declare(strict_types=1);

namespace CoreX\Errors\Contracts;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Data\ProblemDefinition;

#[Api(since: '1.0')]
interface ProblemRegistry
{
    public function get(ErrorCode $code): ProblemDefinition;

    public function fromWire(string $code): ProblemDefinition;

    public function owner(ErrorCode $code): string;

    public function assertOccurrence(ErrorDescriptor $descriptor, ?int $status = null): void;

    /** @return list<ProblemDefinition> */
    public function all(): array;
}
