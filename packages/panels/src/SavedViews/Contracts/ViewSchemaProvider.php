<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Contracts;

use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewSchema;

interface ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema;
}
