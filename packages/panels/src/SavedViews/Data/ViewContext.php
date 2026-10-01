<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;
use CoreX\Context\Data\TenantContext;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ViewContext
{
    public function __construct(public TenantContext $tenant, public string $actorKind, public ?string $actorId)
    {
        if (! in_array($actorKind, ['guest', 'system', 'user'], true)
            || ($actorKind === 'guest' && $actorId !== null)
            || ($actorKind === 'user' && $actorId === null)) {
            throw new InvalidArgumentException('Invalid trusted view actor.');
        }
    }
}
