<?php

declare(strict_types=1);

namespace CoreX\Panels\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceExclusion;
use CoreX\Foundation\Data\ModuleRef;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewScope;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface SavedViewInventory extends ReferenceExclusion
{
    /** The connection used by the supplied public repository. */
    public function connection(DataScope $scope): ConnectionInterface;

    /** All private and shared view namespaces under the held revision in stable order.
     * @return list<array{ViewContext,ViewScope}> */
    public function scopes(DataScope $scope): array;

    /** Old/new field and entity owners, including changed default siblings. Throw on incomplete provenance.
     * A leased, verified reduction may omit only refs removed from the exact approved view.
     *
     * @return list<ModuleRef> */
    public function targets(DataScope $scope, ViewContext $context, string $operation, ?string $id, ?SaveViewInput $input): array;
}
