<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleDependency;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

/**
 * Purge fixture. Owns two tables with a REAL foreign key (stf_children →
 * stf_parents, parent created first) AND, in a later migration, extends the
 * base module's already-existing sba_widgets with a column + index. Exercises:
 *   - FK-safe reverse drop order — child before parent;
 *   - purge across ALL claim types — a `column` and `index` claim on a table
 *     this module does NOT own must be dropped WITHOUT dropping that table
 *    . Requires stub-base so sba_widgets exists to extend.
 */
final class StubFkModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/stub-fk')
            ->title('Stub FK')
            ->tablePrefix('stf')
            ->requires([ModuleDependency::module('corex/stub-base')]);
    }
}
