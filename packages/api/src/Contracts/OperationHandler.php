<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

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
