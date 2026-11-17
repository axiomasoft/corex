<?php

declare(strict_types=1);

namespace CoreX\Audit\Internal;

use CoreX\Audit\Laravel\AuditRegistration;
use CoreX\Audit\Laravel\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Entities\EntityRegistryHook;
use Packman\Modules\Enums\Entities\Capability;

/** Connects CoreX auditing to Packman's projected module entities. */
final readonly class ModuleAuditHook implements EntityRegistryHook
{
    public function wire(EntityRegistry $registry): void
    {
        foreach ($registry->byCapability(Capability::Auditable) as $definition) {
            /** @var class-string<Model> $model */
            $model = $definition->model;

            if (! in_array(HasAuditLog::class, class_uses_recursive($model), true)) {
                AuditRegistration::observe($model);
            }
        }
    }
}
