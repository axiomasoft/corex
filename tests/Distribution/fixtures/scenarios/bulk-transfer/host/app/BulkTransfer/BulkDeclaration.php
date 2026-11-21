<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Operations\Bulk\Laravel\SubmitBulkOperation;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final readonly class BulkDeclaration implements ModuleDiscovery
{
    public const string MODULE = 'laravel/laravel';

    public function __construct(private ModuleDiscovery $installed) {}

    public function discover(): array
    {
        $object = ['type' => 'object'];
        $operations = [Api::operation('fixture.ping', 'GET', '/fixture-ping', Ping::class, 'fixture.ping', $object, $object)];

        if (getenv('COREX_SCENARIO_VARIANT') !== 'equipment') {
            $submit = Api::operation('fixture.bulk', 'POST', '/fixture-bulk', SubmitBulkOperation::class, 'fixture.transfer', $object, $object, successStatus: 202)
                ->asBulkCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 5]);
            $submit->bulkExecutionEnabled = true;
            $operations[] = $submit;
        }

        return [
            ...$this->installed->discover(),
            ModuleManifest::make(self::MODULE)->title('Installed Bulk host')->tablePrefix('fbt')->extends($operations),
        ];
    }
}
