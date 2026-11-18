<?php

declare(strict_types=1);

namespace CoreX\Scheduler;

use CoreX\Scheduler\Internal\SchedulerSchemaSets;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SchedulerModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-scheduler')
            ->title('Scheduler')
            ->description('Durable local wakeup requests.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'sys';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [SchedulerSchemaSets::class];
    }
}
