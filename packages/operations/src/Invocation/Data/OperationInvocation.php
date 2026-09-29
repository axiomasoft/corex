<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Data;

use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Invocation\Contracts\RequestContext;

final readonly class OperationInvocation
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $parameters
     */
    public function __construct(
        public Operation $operation,
        public RequestContext $context,
        public array $input,
        public array $parameters,
        public ?string $idempotencyKey,
    ) {}
}
