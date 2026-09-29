<?php

declare(strict_types=1);

namespace ThirdParty\Panels;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Database\BaseModel;
use CoreX\Modules\Compiler\InstalledPackageVersion;
use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Registry\FieldDefinition;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Contracts\PanelProvider;
use CoreX\Panels\Data\EntityReference;
use CoreX\Panels\Data\PanelAccess;
use CoreX\Panels\Data\PanelContext;
use CoreX\Panels\Data\PanelDescriptor;
use CoreX\Panels\Data\PanelReference;
use RuntimeException;

final class Module
{
    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('thirdparty/custom-panel-provider')
            ->title('Custom panel provider')
            ->description('Contributes one equipment panel descriptor and then freezes.')
            ->tablePrefix('cpp')
            ->extends([
                Entity::make(BaseModel::class)->handle('cpp.equipment')->table('cpp_equipment')->fields([new FieldDefinition('name', 'string')]),
                Entity::make(BaseModel::class)->handle('cpp.site')->table('cpp_sites')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('thirdparty/custom-panel-provider', '1.0.0', 'custom-panel-provider');
    }

    public static function prove(): void
    {
        $provider = new EquipmentPanel;
        $catalogue = new FrozenCatalogue;
        $catalogue->contribute($provider);
        $context = new PanelContext(new TenantContext(new AccountRef('account-1', 'account', 'active', [], [])), 'system', null);
        $listed = $catalogue->forEntity($context, new EntityReference('cpp.equipment', 'eq-1'));
        $frozen = false;

        try {
            $catalogue->contribute($provider);
        } catch (RuntimeException $exception) {
            $frozen = $exception->getMessage() === 'panel catalogue is frozen';
        }

        $after = $catalogue->forEntity($context, new EntityReference('cpp.equipment', 'eq-1'));

        if (! $frozen || count($listed) !== 1 || $listed !== $after || $listed[0]->key !== 'equipment') {
            throw new RuntimeException('Panel contribution was not deterministic across freeze.');
        }
    }
}

final class EquipmentPanel implements PanelProvider
{
    public function descriptor(): PanelDescriptor
    {
        return new PanelDescriptor('panel/1', 'equipment', 'thirdparty/custom-panel-provider', null, ['inspect'], 'Equipment');
    }

    public function access(PanelContext $context, PanelReference $panel): PanelAccess
    {
        if ($panel->panelId !== 'equipment') {
            return PanelAccess::deny('unknown-panel');
        }

        return new PanelAccess(true, ['inspect'], null);
    }
}

final class FrozenCatalogue implements PanelCatalogue
{
    private bool $frozen = false;

    private ?PanelDescriptor $descriptor = null;

    public function contribute(PanelProvider $provider): void
    {
        if ($this->frozen) {
            throw new RuntimeException('panel catalogue is frozen');
        }

        $this->descriptor = $provider->descriptor();
        $this->frozen = true;
    }

    public function forEntity(PanelContext $context, EntityReference $entity): array
    {
        if ($this->descriptor === null || $entity->handle !== 'cpp.equipment') {
            return [];
        }

        return [$this->descriptor];
    }

    public function access(PanelContext $context, PanelReference $panel): PanelAccess
    {
        return $this->descriptor === null
            ? PanelAccess::deny('empty-catalogue')
            : (new EquipmentPanel)->access($context, $panel);
    }
}
