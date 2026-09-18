<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use CoreX\Support\Data\DataScope;
use InvalidArgumentException;
use Override;

final readonly class LocalizedSearchSchema implements SearchSchema
{
    /**
     * @param  list<LocalizedFieldBinding>  $bindings
     */
    public function __construct(
        private SearchSchema $base,
        private array $bindings,
        private string $localeTag,
    ) {
        foreach ($bindings as $binding) {
            if (! $binding instanceof LocalizedFieldBinding) {
                throw new InvalidArgumentException('Localized search bindings must be typed field bindings.');
            }
        }
    }

    #[Override]
    public function describe(DataScope $scope, string $entity): SearchSnapshot
    {
        $snapshot = $this->base->describe(scope: $scope, entity: $entity);
        $searchable = $snapshot->searchableColumns;
        $results = $snapshot->resultColumns;

        foreach ($this->bindings as $binding) {
            $projected = $binding->column.'__'.$this->localeTag;

            if (! in_array($projected, $searchable, true)) {
                $searchable[] = $projected;
            }

            if (! in_array($projected, $results, true)) {
                $results[] = $projected;
            }
        }

        $schemaHash = hash('sha256', implode('|', [
            $snapshot->schemaHash,
            $this->localeTag,
            ...array_map(static fn (LocalizedFieldBinding $binding): string => $binding->column.':'.$binding->schemaHash, $this->bindings),
        ]));

        return new SearchSnapshot(
            entity: $snapshot->entity,
            schemaHash: $schemaHash,
            table: $snapshot->table,
            idColumn: $snapshot->idColumn,
            searchableColumns: $searchable,
            filterColumns: $snapshot->filterColumns,
            resultColumns: $results,
        );
    }
}
