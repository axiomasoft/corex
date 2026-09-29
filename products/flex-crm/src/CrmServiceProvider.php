<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Deal\Models\Stage;
use Flex\Crm\Lead\Models\Lead;
use Flex\Crm\Support\CrmOptions;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/flex-crm.php', 'flex-crm');

        $raw = $this->app['config']->get('flex-crm', []);
        $options = CrmOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(CrmOptions::class, $options);
    }

    public function boot(): void
    {
        // Register entities so they enter axiomasoft/corex's morph map (built in
        // ModulesServiceProvider's app.booted callback, D22).
        $registry = $this->app->make(ArrayEntityRegistry::class);

        $registry->register(new EntityDefinition(
            handle: 'crm.pipeline',
            model: Pipeline::class,
            table: 'crm_pipelines',
            module: 'flex/crm',
            labelSingular: 'Pipeline',
            labelPlural: 'Pipelines',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.stage',
            model: Stage::class,
            table: 'crm_stages',
            module: 'flex/crm',
            labelSingular: 'Stage',
            labelPlural: 'Stages',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.deal',
            model: Deal::class,
            table: 'crm_deals',
            module: 'flex/crm',
            labelSingular: 'Deal',
            labelPlural: 'Deals',
            capabilities: [Capability::Auditable],
            fields: [],
            titleAttribute: 'title',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.company',
            model: Company::class,
            table: 'crm_companies',
            module: 'flex/crm',
            labelSingular: 'Company',
            labelPlural: 'Companies',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.contact',
            model: Contact::class,
            table: 'crm_contacts',
            module: 'flex/crm',
            labelSingular: 'Contact',
            labelPlural: 'Contacts',
            capabilities: [],
            fields: [],
            titleAttribute: 'display_name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.lead',
            model: Lead::class,
            table: 'crm_leads',
            module: 'flex/crm',
            labelSingular: 'Lead',
            labelPlural: 'Leads',
            capabilities: [],
            fields: [],
            titleAttribute: 'title',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.activity',
            model: Activity::class,
            table: 'crm_activities',
            module: 'flex/crm',
            labelSingular: 'Activity',
            labelPlural: 'Activities',
            capabilities: [Capability::Auditable],
            fields: [],
            titleAttribute: 'subject',
        ));
    }
}
