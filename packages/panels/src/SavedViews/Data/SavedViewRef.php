<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class SavedViewRef
{
    public function __construct(public string $id, public ViewScope $scope, public string $kind, public string $revision, public bool $isDefault) {}
}
