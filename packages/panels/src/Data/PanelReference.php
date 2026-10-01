<?php

declare(strict_types=1);

namespace CoreX\Panels\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class PanelReference
{
    public function __construct(public string $providerKey, public string $panelId, public ?EntityReference $parent)
    {
        if ($providerKey === '' || $panelId === '') {
            throw new InvalidArgumentException('Panel references require a provider and panel id.');
        }
    }
}
