<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ViewSubject
{
    public function __construct(public ViewScope $scope, public ?string $viewId) {}
}
