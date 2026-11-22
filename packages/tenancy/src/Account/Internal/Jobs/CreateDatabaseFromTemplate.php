<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal\Jobs;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Account\Laravel\AccountTemplateSnapshot;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks as TemplateIntegrityVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Packman\Attributes\Internal;

/** Delegates synchronous, idempotent materialization to the physical owner. */
#[Internal]
final class CreateDatabaseFromTemplate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly AccountRef $account,
        public readonly ProvisionParams $params,
    ) {}

    public function handle(
        TemplateIntegrityVerifier $templateIntegrityVerifier,
        TenancyOptions $options,
        AccountResources $resources,
    ): true {
        $snapshot = AccountTemplateSnapshot::capture(account: $this->account, centralConnection: $options->centralConnection);

        $templateIntegrityVerifier->verifyCurrent(
            version: $snapshot->templateVersion,
            clusterId: $snapshot->clusterId,
        );

        if ($resources->exists($this->account)) {
            $templateIntegrityVerifier->verifyAccount($snapshot);

            return true;
        }

        $resources->provision(account: $this->account, params: $this->params);

        return true;
    }
}
