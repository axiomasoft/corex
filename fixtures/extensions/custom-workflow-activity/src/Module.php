<?php

declare(strict_types=1);

namespace ThirdParty\Workflow;

use CoreX\Database\Laravel\BaseModel;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\ExtensionStamp;
use CoreX\Modules\Data\FieldDefinition;
use CoreX\Modules\Data\InstalledPackageVersion;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Internal\Extend\Workflow;
use CoreX\Modules\Testing\ExtensionContract;
use RuntimeException;

final class MaintenanceActivity {}

final class Module
{
    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('thirdparty/custom-workflow-activity')
            ->title('Custom workflow activity')
            ->description('Contributes a versioned maintenance activity.')
            ->tablePrefix('cwa')
            ->extends([
                Entity::make(BaseModel::class)->handle('cwa.equipment')->table('cwa_equipment')->workflowable()->fields([new FieldDefinition('name', 'string')]),
                Workflow::stepType(MaintenanceActivity::class),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('thirdparty/custom-workflow-activity', '1.0.0', 'custom-workflow-activity');
    }

    public static function prove(): void
    {
        $manifest = self::manifest();
        $reversed = self::manifest();
        $reversed->extenders = array_reverse($reversed->extenders);
        $fingerprint = hash('sha256', 'workflow-registry');
        $stored = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/1', $fingerprint);
        $sameImplementation = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/1', hash('sha256', 'other-registry'));
        $mismatch = new ExtensionStamp('workflow.step', MaintenanceActivity::class, 'maintenance/2', $fingerprint);

        if (self::stepIds($manifest) !== self::stepIds($reversed)) {
            throw new RuntimeException('Workflow activity identities changed when registration order was reversed.');
        }

        if (! in_array(MaintenanceActivity::class, self::stepIds($manifest), true)) {
            throw new RuntimeException('Maintenance activity was not contributed.');
        }

        if (! ExtensionContract::sameImplementation($stored, $sameImplementation) || ExtensionContract::sameImplementation($stored, $mismatch)) {
            throw new RuntimeException('Workflow version stamp did not reject a mismatched implementation.');
        }
    }

    /** @return list<string> */
    private static function stepIds(ModuleManifest $manifest): array
    {
        $ids = [];

        foreach ($manifest->extenders as $extender) {
            if ($extender instanceof Workflow && $extender->kind === 'stepType' && is_string($extender->stepClass)) {
                $ids[] = $extender->stepClass;
            }
        }

        sort($ids);

        return $ids;
    }
}
