<?php

declare(strict_types=1);

namespace CoreX\Departments\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Departments\Internal\Models\Department;
use Illuminate\Database\Query\Builder;

/** Composable hierarchy reads for access predicates. Eloquent scopes are applied before exposing the SQL query; no concrete model leaves this module. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DepartmentQueries
{
    public static function table(): string
    {
        return (new Department)->getTable();
    }

    public static function path(string $id): ?string
    {
        $path = Department::query()->where('id', $id)->value('path');

        return is_string($path) ? $path : null;
    }

    public static function subtreeIds(string $path): Builder
    {
        return Department::query()->toBase()
            ->whereRaw('path <@ ?::ltree', [$path])->select('id');
    }

    public static function contains(string $id, string $path): bool
    {
        return Department::query()->toBase()
            ->where('id', $id)->whereRaw('path <@ ?::ltree', [$path])->exists();
    }
}
