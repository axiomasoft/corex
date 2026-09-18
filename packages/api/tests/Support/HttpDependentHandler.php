<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\RequestContextProvider;

final class HttpDependentHandler implements OperationHandler
{
    public function __construct(private readonly RequestContextProvider $contexts) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $resolved = $this->contexts->current();

        return ['actor' => $resolved->principal()->id()];
    }
}
