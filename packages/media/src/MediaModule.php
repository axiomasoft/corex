<?php

declare(strict_types=1);

namespace CoreX\Media;

use CoreX\Media\Internal\MediaSchemaSets;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class MediaModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-media')
            ->title('Media')
            ->description('Tenant-scoped private media lifecycle primitives.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'med';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [MediaSchemaSets::class];
    }
}
