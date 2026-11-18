<?php

declare(strict_types=1);

namespace CoreX\Workflow;

use CoreX\Workflow\Internal\WorkflowSchemaSets;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WorkflowModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-workflow')
            ->title('Workflow')
            ->description('Optional durable workflow engine binding.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'wfl';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [WorkflowSchemaSets::class];
    }
}
