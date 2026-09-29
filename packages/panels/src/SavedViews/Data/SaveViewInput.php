<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

final readonly class SaveViewInput
{
    /**
     * @param  array<string, string>  $localizedName
     * @param  array<string, mixed>  $config
     */
    public function __construct(public ?string $id, public string $expectedRevision, public ViewScope $scope, public string $kind, public array $localizedName, public array $config) {}
}
