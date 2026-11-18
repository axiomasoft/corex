<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\SearchIntl\Data\LocalizedFieldBinding;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;

#[Internal]
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
            self::validateBinding($binding);
        }
    }

    private static function validateBinding(mixed $binding): void
    {
        if (! $binding instanceof LocalizedFieldBinding) {
            throw new InvalidArgumentException('Localized search bindings must be typed field bindings.');
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
