<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\ToolCatalogueView;

interface ToolCatalogue
{
    public function discover(RequestContext $context): ToolCatalogueView;
}
