<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Internal;

use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewSchema;
use LogicException;

/** @internal */
final class UnavailableViewSchemaProvider implements ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema
    {
        throw new LogicException('No saved-view schema provider is configured.');
    }
}
