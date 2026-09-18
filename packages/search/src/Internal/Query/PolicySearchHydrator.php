<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Query;

use Closure;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Support\CandidatePage;
use CoreX\Search\Support\CurrentSearchRecord;
use CoreX\Search\Support\SearchPage;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;

final readonly class PolicySearchHydrator implements SearchHydrator
{
    /**
     * @param  Closure(DataScope, RecordRef, SearchSnapshot): ?CurrentSearchRecord  $loadCurrent
     */
    public function __construct(
        private Closure $loadCurrent,
        private DataScopeAuthorizer $authorizer,
    ) {}

    public function authorize(DataScope $scope, CandidatePage $candidates, SearchSnapshot $schema): SearchPage
    {
        $records = [];

        foreach ($candidates->candidates as $candidate) {
            $current = ($this->loadCurrent)($scope, $candidate->record, $schema);

            if (! $current instanceof CurrentSearchRecord
                || $current->record->entity !== $candidate->record->entity
                || $current->record->id !== $candidate->record->id
                || ! hash_equals($schema->schemaHash, $current->schemaHash)
                || ! $this->authorizer->authorize(
                    scope: $scope,
                    entity: $current->record->entity,
                    subjectId: $current->record->id,
                    fields: [],
                    action: 'search.hydrate',
                )->allowed) {
                continue;
            }

            $records[] = array_intersect_key($current->fields, array_flip($schema->resultColumns));
        }

        return new SearchPage(records: $records, nextAfterId: $candidates->nextAfterId, truncated: $candidates->truncated);
    }
}
