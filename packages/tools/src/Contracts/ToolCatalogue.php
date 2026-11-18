<?php

declare(strict_types=1);

namespace CoreX\Tools\Contracts;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Tools\Data\ToolCatalogueView;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
interface ToolCatalogue
{
    public function discover(RequestContext $context): ToolCatalogueView;
}
