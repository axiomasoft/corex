<?php

declare(strict_types=1);

namespace CoreX\Modules\Lifecycle;

use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Enums\ModuleState;
use CoreX\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * DB-backed {@see ModuleStateReader} (closes the P1.5 seam): reads the
 * enabled set straight from `mod_modules`. `$ctx` is unused for now — P1 has
 * one physical database per install (boxed or single-tenant); the
 * per-account DB-per-account model that gives `$ctx` meaning ships in P2
 * (corex/tenancy, D13). `$connection` is null (app default) unless a caller
 * pins an explicit name (tests).
 */
final class DatabaseModuleStateReader implements ModuleStateReader
{
    public function __construct(private readonly ?string $connection = null) {}

    /** @return array<string, array{state: string, edition: string}> */
    public function enabledFor(TenantContext $ctx): array
    {
        return DB::connection($this->connection)->table('mod_modules')
            ->where('state', ModuleState::Enabled->value)
            ->get(['name', 'state', 'edition'])
            ->mapWithKeys(static fn (object $row): array => [
                $row->name => ['state' => $row->state, 'edition' => $row->edition],
            ])
            ->all();
    }
}
