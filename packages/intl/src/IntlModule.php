<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Intl\Internal\IntlSchemaSets;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IntlModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('axiomasoft/corex-intl')
            ->title('Internationalization')
            ->description('Typed locale, money/rate snapshots, and offline geography reference utilities.')
            ->tablePrefix($this->tablePrefix());
    }

    public function tablePrefix(): string
    {
        return 'int';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [IntlSchemaSets::class];
    }
}
