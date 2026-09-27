<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\SavedViews\Data\ViewContext;
use CoreX\SavedViews\Data\ViewSchema;

interface ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema;
}
