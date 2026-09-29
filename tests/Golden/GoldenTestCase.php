<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden;

use AzGuard\AzGuardServiceProvider;
use CoreX\Access\CoreAuthServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\CustomFields\AttributesServiceProvider;
use CoreX\Intl\IntlServiceProvider;
use CoreX\Media\MediaServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchIntl\SearchIntlServiceProvider;
use CoreX\SearchMeilisearch\MeilisearchServiceProvider;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\TasksWorkflow\TasksWorkflowServiceProvider;
use CoreX\Tenancy\TenancyServiceProvider;
use CoreX\Tests\TestCase;
use CoreX\Tools\ToolServiceProvider;
use CoreX\Transfer\TransferServiceProvider;
use CoreX\Wakeup\WakeupServiceProvider;
use CoreX\Workflow\WorkflowServiceProvider;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
            ...$this->manifestProviders(),
        ];
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
