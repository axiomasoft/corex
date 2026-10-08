<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCall;
use CoreX\Tools\Data\ToolDescriptor;
use CoreX\Tools\Data\ToolResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ToolExecutionGuard
{
    /**
     * A local domain-key guard must run the effect and receipt in one transaction.
     * A non-committed callback result (including a caught handler/output error)
     * must roll back that transaction before returning NotExecuted. Returning
     * the result normally from a transaction closure commits pending writes.
     * Exceptions must also roll back. Provider-key effects cannot be rolled back
     * locally: preserve Committed or Unknown and reconcile using the same key.
     *
     * @param  Closure():ToolResult  $effect
     */
    public function execute(ToolDescriptor $tool, RequestContext $context, ToolCall $call, Closure $effect): ToolResult;
}
