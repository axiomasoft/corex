<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use CoreX\Access\AccessServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\CustomFields\CustomFieldsServiceProvider;
use CoreX\ImportExport\ImportExportServiceProvider;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Media\MediaServiceProvider;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\RecurringTasks\RecurringTasksServiceProvider;
use CoreX\Scheduler\SchedulerServiceProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchCustomFields\SearchCustomFieldsServiceProvider;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchMeilisearch\SearchMeilisearchServiceProvider;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tests\TestCase;
use CoreX\Tools\ToolsServiceProvider;
use CoreX\Workflow\WorkflowServiceProvider;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Support\ServiceProvider;
use Mockery;
use Packman\Modules\ModulesServiceProvider;
use RuntimeException;
use Tenant\Contracts\Runtime\TenantRuntime;

abstract class GoldenTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AccessServiceProvider::class,
            CustomFieldsServiceProvider::class,
            CoreServiceProvider::class,
            CrmServiceProvider::class,
            IntlServiceProvider::class,
            MediaServiceProvider::class,
            ModulesServiceProvider::class,
            NotificationsServiceProvider::class,
            PanelsServiceProvider::class,
            SearchServiceProvider::class,
            SearchCustomFieldsServiceProvider::class,
            SearchIntlServiceProvider::class,
            SearchMeilisearchServiceProvider::class,
            TasksServiceProvider::class,
            TasksWorkflowServiceProvider::class,
            RecurringTasksServiceProvider::class,
            TenancyServiceProvider::class,
            ImportExportServiceProvider::class,
            SchedulerServiceProvider::class,
            WorkflowServiceProvider::class,
            OperationsServiceProvider::class,
            ToolsServiceProvider::class,
            ...$this->manifestProviders(),
        ];
    }

    /** Config-only tests declare host ports; unexpected runtime calls fail on these mocks. */
    protected function declareTenancyHostConfiguration(): void
    {
        $app = $this->app ?? throw new RuntimeException('Host configuration requires a bootstrapped application.');

        foreach ([
            TenantRuntime::class,
            AccountIsolationMapper::class,
            AccountResources::class,
            TemplateIntegrityChecks::class,
            TemplateIntegrityVerifier::class,
        ] as $contract) {
            $app->instance($contract, Mockery::mock($contract));
        }
    }

    /** @return list<class-string> */
    private function manifestProviders(): array
    {
        $manifest = json_decode((string) file_get_contents(__DIR__.'/../../packages/webhooks/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $extra = is_array($manifest) ? ($manifest['extra'] ?? []) : [];
        $laravel = is_array($extra) ? ($extra['laravel'] ?? []) : [];
        $declared = is_array($laravel) ? ($laravel['providers'] ?? []) : [];

        if (! is_array($declared)) {
            throw new RuntimeException('Package providers must be an array.');
        }

        $providers = [];

        foreach ($declared as $provider) {
            if (! is_string($provider) || ! is_subclass_of($provider, ServiceProvider::class)) {
                throw new RuntimeException('Package provider must extend ServiceProvider.');
            }

            $providers[] = $provider;
        }

        return $providers;
    }
}
