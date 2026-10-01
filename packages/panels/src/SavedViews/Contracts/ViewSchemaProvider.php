<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewSchema;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ViewSchemaProvider
{
    public function schema(ViewContext $context, string $entityHandle): ViewSchema;
}
