<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal;

use CoreX\Context\Contracts\TenantDatabaseProvisioner;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use Stancl\Tenancy\Events\TenantCreated;

/**
 * Fixes embedding_model/dim (and cluster/template/vertical/locale) onto the
 * `root_accounts` row FROM {@see ProvisionParams} at provisioning time
 * (AC-10, R-16 — never from global config on the fly), then fires
 * `TenantCreated` to trigger the stancl JobPipeline
 * ({@see CoreX\Tenancy\Account\Internal\Jobs\CreateDatabaseFromTemplate}). Idempotent/
 * replayable (premortem B4): a repeated call re-fires the pipeline, whose
 * job no-ops if the database already exists.
 */
final class StanclTenantDatabaseProvisioner implements TenantDatabaseProvisioner
{
    public function __construct(
        private readonly string $centralConnection,
        private readonly TemplateIntegrityChecks $templateIntegrityVerifier,
    ) {}

    public function provision(ProvisionParams $params): void
    {
        $this->templateIntegrityVerifier->verifyCurrent(
            version: $params->templateVersion,
            clusterId: $params->clusterId,
        );

        $account = Account::on($this->centralConnection)->findOrFail($params->accountId);

        $account->forceFill([
            'cluster_id' => $params->clusterId,
            'template_version' => $params->templateVersion,
            'embedding_model' => $params->embeddingModel,
            'embedding_dim' => $params->embeddingDim,
            'vertical_code' => $params->verticalCode,
            'locale' => $params->locale,
        ])->save();

        event(new TenantCreated($account));
    }
}
