<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;

/** @corex-model-extension Exact Account template_version, cluster_id and db_name columns; Account remains final/private. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccountTemplateSnapshot
{
    public static function capture(AccountRef $account, string $centralConnection): TemplateAccountView
    {
        $tenant = Account::on($centralConnection)->findOrFail($account->id);

        return new TemplateAccountView(templateVersion: $tenant->template_version, clusterId: $tenant->cluster_id, databaseName: $tenant->db_name);
    }
}
