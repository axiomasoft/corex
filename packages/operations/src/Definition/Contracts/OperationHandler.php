<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Invocation\Contracts\RequestContext;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationHandler
{
    /**
     * Map validated input to module-owned DTOs/Actions. Context is separate from untrusted input.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $parameters
     * @return array<string, mixed>|list<mixed>
     */
    public function handle(RequestContext $context, array $input, array $parameters): array;
}
