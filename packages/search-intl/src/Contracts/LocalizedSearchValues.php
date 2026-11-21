<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Contracts;

use CoreX\Data\Data\RecordRef;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\SearchIntl\Projection\Data\LocalizedFieldBinding;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Supplies approved localized Search fields and current values from the record owner. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface LocalizedSearchValues
{
    /**
     * Supplies all locale shapes and their fallback policy for this entity.
     *
     * @return list<LocaleResolution>
     */
    public function locales(ProjectionContext $context): array;

    /**
     * Describes the owner schema independently of the requesting principal.
     *
     * @return list<LocalizedFieldBinding>
     */
    public function fields(ProjectionContext $context): array;

    public function load(ProjectionContext $context, LocalizedFieldBinding $field, RecordRef $record): ?LocalizedText;
}
