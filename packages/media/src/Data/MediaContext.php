<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Context\Data\TenantContext;
use InvalidArgumentException;

final readonly class MediaContext
{
    public function __construct(public TenantContext $tenant, public string $actorKind, public ?string $actorId)
    {
        if ($actorKind === '' || ($actorKind === 'user' && ($actorId === null || $actorId === ''))) {
            throw new InvalidArgumentException('Invalid trusted media actor.');
        }
    }
}
