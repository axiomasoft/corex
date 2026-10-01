<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class ToolCatalogueView
{
    /**
     * @param  list<ToolDescriptor>  $descriptors
     */
    public function __construct(
        public string $revision,
        public array $descriptors,
    ) {}
}
