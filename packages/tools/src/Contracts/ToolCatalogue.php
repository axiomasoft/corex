<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCatalogueView;

interface ToolCatalogue
{
    public function discover(RequestContext $context): ToolCatalogueView;
}
