<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolPolicy;
use CoreX\Api\Data\JsonObject;
use CoreX\Api\Data\ToolDescriptor;

final class ToolPolicyFixture implements ToolPolicy
{
    public bool $allowDiscover = false;

    public bool $allowInvoke = false;

    /** @var list<array{action: string, principal: string, account: string, code: string}> */
    public array $calls = [];

    public function discover(ToolDescriptor $tool, RequestContext $context): bool
    {
        $this->calls[] = [
            'action' => 'discover',
            'principal' => $context->principal()->id(),
            'account' => $context->tenant()->account->id,
            'code' => $tool->code,
        ];

        return $this->allowDiscover;
    }

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
    {
        $this->calls[] = [
            'action' => 'invoke',
            'principal' => $context->principal()->id(),
            'account' => $context->tenant()->account->id,
            'code' => $tool->code,
        ];

        return $this->allowInvoke;
    }
}
