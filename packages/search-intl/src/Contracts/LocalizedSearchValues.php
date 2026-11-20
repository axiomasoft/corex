<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Contracts;

use CoreX\Data\Data\RecordRef;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Supplies approved localized Search fields and current values from the record owner. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface LocalizedSearchValues
{
    public function locale(ProjectionContext $context): LocaleResolution;

    /** @return list<LocalizedFieldBinding> */
    public function fields(ProjectionContext $context): array;

    public function load(ProjectionContext $context, LocalizedFieldBinding $field, RecordRef $record): ?LocalizedText;
}
