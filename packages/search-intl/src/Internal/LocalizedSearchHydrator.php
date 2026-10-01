<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\SearchPage;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use Override;

final readonly class LocalizedSearchHydrator implements SearchHydrator
{
    /**
     * @param  list<LocalizedFieldBinding>  $bindings
     * @param  Closure(DataScope, LocalizedFieldBinding, RecordRef):?LocalizedText  $loadLocalized
     */
    public function __construct(
        private SearchHydrator $base,
        private LocalizedFieldProjector $projector,
        private array $bindings,
        private LocaleResolution $locale,
        private Closure $loadLocalized,
    ) {}

    #[Override]
    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage
    {
        $page = $this->base->authorize(scope: $scope, candidates: $candidates, schema: $schema);

        if ($page->records === [] || $this->bindings === []) {
            return $page;
        }

        $records = [];

        foreach ($page->records as $record) {
            $localized = $record;
            $recordRef = new RecordRef(entity: $schema->entity, id: (string) ($record['id'] ?? ''));

            foreach ($this->bindings as $binding) {
                $projected = $binding->column.'__'.$this->locale->used;
                $text = ($this->loadLocalized)($scope, $binding, $recordRef);

                if (! $text instanceof LocalizedText) {
                    continue;
                }

                $value = $this->projector->project(text: $text, locale: $this->locale);

                if ($value !== null) {
                    $localized[$projected] = $value;
                }
            }

            $records[] = array_intersect_key($localized, array_flip($schema->resultColumns));
        }

        return new SearchPage(records: $records, nextAfterId: $page->nextAfterId, truncated: $page->truncated);
    }
}
