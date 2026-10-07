<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Lifecycle;

use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Enums\ModuleState;
use Illuminate\Database\ConnectionResolverInterface;
use stdClass;

/**
 * DB-backed {@see ModuleStateReader}: reads the enabled set straight from
 * `mod_modules`. `$ctx` is unused for now — the current phase has one
 * physical database per install (boxed or single-tenant); a later
 * per-account DB-per-account model (axiomasoft/corex-tenancy) will give `$ctx`
 * meaning. `$connection` is null (app default) unless a caller pins an
 * explicit name (tests).
 *
 * @internal spec: P1.5, D13
 */
final readonly class DatabaseModuleStateReader implements ModuleStateReader
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private ?string $connection = null,
    ) {}

    /** @return array<string, array{state: string, edition: string}> */
    public function enabledFor(TenantContext $ctx): array
    {
        return $this->connections->connection($this->connection)->table('mod_modules')
            ->where('state', ModuleState::Enabled->value)
            ->get(['name', 'state', 'edition'])
            ->mapWithKeys(static function (stdClass $row): array {
                assert(is_string($row->name) && is_string($row->state) && is_string($row->edition));

                return [$row->name => ['state' => $row->state, 'edition' => $row->edition]];
            })
            ->all();
    }
}
