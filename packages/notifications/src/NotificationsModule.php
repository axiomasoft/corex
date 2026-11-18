<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Notifications\Internal\NotificationsSchemaSets;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-notifications')
            ->title('Notifications')
            ->description('Durable tenant-scoped notification primitives.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'ntf';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [NotificationsSchemaSets::class];
    }
}
