<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;

#[Api(since: '1.0')]
final readonly class SaveViewInput
{
    /**
     * @param  array<string, string>  $localizedName
     */
    public function __construct(public ?string $id, public string $expectedRevision, public ViewScope $scope, public string $kind, public array $localizedName, public JsonDocument $config) {}
}
