<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;

/** @corex-model-extension Exact Stancl Account template_version, cluster_id and db_name columns; Account remains final/private. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccountTemplateSnapshot
{
    public static function capture(TenantWithDatabase $tenant): TemplateAccountView
    {
        assert($tenant instanceof Account);

        return new TemplateAccountView($tenant->template_version, $tenant->cluster_id, $tenant->db_name);
    }
}
