<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ViewSchema
{
    /** @param list<ViewField> $fields */
    public function __construct(public string $version, public string $entityHandle, public array $fields) {}
}
