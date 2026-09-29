<?php

declare(strict_types=1);

namespace CoreX\Tools\Data;

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
