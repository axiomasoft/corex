<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal;

use CoreX\Context\Contracts\TenantDatabaseProvisioner;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Account\Internal\Jobs\CreateDatabaseFromTemplate;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;

/**
 * Persists verified provisioning parameters, then clones synchronously. Replays verify the
 * existing account database instead of cloning again.
 */
final readonly class DatabaseTenantProvisioner implements TenantDatabaseProvisioner
{
    public function __construct(
        private string $centralConnection,
        private TemplateIntegrityChecks $templateIntegrityVerifier,
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

        dispatch_sync(new CreateDatabaseFromTemplate(account: new AccountRef(id: $account->id, slug: $account->slug ?? '', status: $account->status, features: [], limits: []), params: $params));
    }
}
