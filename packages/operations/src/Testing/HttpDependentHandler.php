<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class HttpDependentHandler implements OperationHandler
{
    public function __construct(private RequestContextProvider $contexts) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        $resolved = $this->contexts->current();

        return ['actor' => $resolved->principal()->principalId()];
    }
}
