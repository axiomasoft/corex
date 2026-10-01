<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal\Jobs;

use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks as TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Provisioning pipeline step (`TenantCreated ⇒ JobPipeline::make([self::class])`,
 * R-11 §2.3 п.5) — deliberately WITHOUT `MigrateDatabase`/`SeedDatabase`,
 * the schema already lives in the golden template. Idempotent: a second run
 * against an already-provisioned account no-ops (replayable pipeline,
 * premortem B4) instead of re-cloning or erroring.
 */
final class CreateDatabaseFromTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Account $tenant,
    ) {}

    public function handle(
        TemplateIntegrityVerifier $templateIntegrityVerifier,
        TenancyOptions $options,
    ): bool {
        $tenant = Account::on($options->centralConnection)->findOrFail($this->tenant->id);

        $templateIntegrityVerifier->verifyCurrent(
            version: (int) $tenant->template_version,
            clusterId: (string) $tenant->cluster_id,
        );

        $manager = $tenant->database()->manager();
        $name = $tenant->database()->getName();

        if ($manager->databaseExists($name)) {
            $templateIntegrityVerifier->verifyAccount(new TemplateAccountView($tenant->template_version, $tenant->cluster_id, $tenant->db_name));

            return true;
        }

        return $manager->createDatabase($tenant);
    }
}
