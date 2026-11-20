<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Query;

use Closure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Data\CandidatePage;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\SearchPage;
use CoreX\Search\Data\SearchSnapshot;
use Packman\Attributes\Internal;

#[Internal]
final readonly class PolicySearchHydrator implements SearchHydrator
{
    /**
     * @param  Closure(DataScope, RecordRef, SearchSnapshot):?CurrentSearchRecord  $loadCurrent
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
                || ! hash_equals($schema->schemaHash, $current->schemaHash)) {
                continue;
            }

            if (! $this->authorizer->authorize($scope, $current->record, new FieldSelection([]), FieldUse::Read)->allowed) {
                continue;
            }

            $projection = array_intersect_key($current->fields, array_flip($schema->resultColumns));

            if ($projection !== [] && ! $this->authorizer->authorize(
                scope: $scope,
                record: $current->record,
                fields: new FieldSelection(array_keys($projection)),
                use: FieldUse::Read,
            )->allowed) {
                continue;
            }

            $records[] = $projection;
        }

        return new SearchPage(records: $records, nextAfterId: $candidates->nextAfterId, truncated: $candidates->truncated);
    }
}
