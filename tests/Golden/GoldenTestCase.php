<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use AzGuard\AzGuardServiceProvider;
use CoreX\Api\ApiServiceProvider;
use CoreX\Api\ToolServiceProvider;
use CoreX\Attributes\AttributesServiceProvider;
use CoreX\Auth\CoreAuthServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Media\MediaServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\Search\Meilisearch\MeilisearchServiceProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tests\TestCase;
use CoreX\Transfer\TransferServiceProvider;
use CoreX\Wakeup\WakeupServiceProvider;
use CoreX\Workflow\WorkflowServiceProvider;
use Flex\Crm\CrmServiceProvider;

abstract class GoldenTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AzGuardServiceProvider::class,
            CoreAuthServiceProvider::class,
            AttributesServiceProvider::class,
            CoreServiceProvider::class,
            CrmServiceProvider::class,
            IntlServiceProvider::class,
            MediaServiceProvider::class,
            ModulesServiceProvider::class,
            NotificationsServiceProvider::class,
            PanelsServiceProvider::class,
            SearchServiceProvider::class,
            SearchIntlServiceProvider::class,
            MeilisearchServiceProvider::class,
            TasksServiceProvider::class,
            TasksWorkflowServiceProvider::class,
            TenancyServiceProvider::class,
            TransferServiceProvider::class,
            WakeupServiceProvider::class,
            WorkflowServiceProvider::class,
            ApiServiceProvider::class,
            ToolServiceProvider::class,
        ];
    }
}
