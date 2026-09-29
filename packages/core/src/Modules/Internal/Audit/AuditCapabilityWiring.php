<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Audit;

use CoreX\Audit\HasAuditLog;
use CoreX\Audit\Internal\AuditObserver;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Enums\Capability;
use Illuminate\Database\Eloquent\Model;

use function class_uses_recursive;

/**
 * Attaches CoreX\Audit\Internal\AuditObserver to every model registered with
 * capability Auditable. AuditObserver itself lives in axiomasoft/corex, but this
 * lookup needs EntityRegistry — axiomasoft/corex cannot depend on axiomasoft/corex
 * (the reverse of this package's own require would cycle) — so the wiring
 * lives here, called from ModulesServiceProvider::boot(). Skips models
 * that already opt in manually via HasAuditLog, so a model is never
 * recorded twice.
 *
 * Extracted to a standalone method (not inlined in the provider) so it is
 * testable independently of Testbench's provider-boot lifecycle.
 *
 * @internal spec: B-10 §4.4, D22
 */
final class AuditCapabilityWiring
{
    public static function wire(EntityRegistry $registry): void
    {
        foreach ($registry->byCapability(Capability::Auditable) as $definition) {
            /** @var class-string<Model> $model */
            $model = $definition->model;

            if (! in_array(HasAuditLog::class, class_uses_recursive($model), true)) {
                $model::observe(AuditObserver::class);
            }
        }
    }
}
