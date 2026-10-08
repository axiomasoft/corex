<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Attributes\Api;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCatalogueView;

#[Api(since: '1.0')]
interface ToolCatalogue
{
    public function discover(RequestContext $context): ToolCatalogueView;
}
