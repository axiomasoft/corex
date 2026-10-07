<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\StaticCall;

use Carbon\Carbon;
use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Facades\Date;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Type\ObjectType;

final class CarbonToDateFacadeRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    public function refactor(Node $node): ?StaticCall
    {
        if (! $node instanceof StaticCall) {
            return null;
        }

        if (! $this->isCarbon($node->class)) {
            return null;
        }

        return new StaticCall(new FullyQualified(Date::class), $node->name, $node->args, $node->getAttributes());
    }

    private function isCarbon(Node $node): bool
    {
        return $this->isObjectType($node, new ObjectType(Carbon::class)) || $this->isObjectType($node, new ObjectType(\Illuminate\Support\Carbon::class));
    }
}
