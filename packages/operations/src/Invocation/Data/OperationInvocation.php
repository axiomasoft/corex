<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Definition\Data\Operation;

#[Api(since: '1.0')]
final readonly class OperationInvocation
{
    /**
     * @param  array<string, string>  $parameters
     */
    public function __construct(public Operation $operation, public RequestContext $context, public JsonDocument $input, public array $parameters, public ?string $idempotencyKey) {}
}
