<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class HttpDependentHandler implements OperationHandler
{
    public function __construct(private readonly RequestContextProvider $contexts) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $resolved = $this->contexts->current();

        return ['actor' => $resolved->principal()->id()];
    }
}
