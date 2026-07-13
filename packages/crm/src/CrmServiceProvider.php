<?php

declare(strict_types=1);

namespace CoreX\Crm;

use CoreX\Crm\Models\Activity;
use CoreX\Crm\Models\Company;
use CoreX\Crm\Models\Contact;
use CoreX\Crm\Models\Deal;
use CoreX\Crm\Models\Lead;
use CoreX\Crm\Models\Pipeline;
use CoreX\Crm\Models\Stage;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\EntityDefinition;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register entities so they enter flex/modules's morph map (built in
        // ModulesServiceProvider's app.booted callback, D22).
        $registry = $this->app->make(ArrayEntityRegistry::class);

        $registry->register(new EntityDefinition(
            handle: 'crm.pipeline',
            model: Pipeline::class,
            table: 'crm_pipelines',
            module: 'corex/crm',
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
            module: 'corex/crm',
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
            module: 'corex/crm',
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
            module: 'corex/crm',
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
            module: 'corex/crm',
            labelSingular: 'Contact',
            labelPlural: 'Contacts',
            capabilities: [],
            fields: [],
            titleAttribute: null,
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.lead',
            model: Lead::class,
            table: 'crm_leads',
            module: 'corex/crm',
            labelSingular: 'Lead',
            labelPlural: 'Leads',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        ));

        $registry->register(new EntityDefinition(
            handle: 'crm.activity',
            model: Activity::class,
            table: 'crm_activities',
            module: 'corex/crm',
            labelSingular: 'Activity',
            labelPlural: 'Activities',
            capabilities: [Capability::Auditable],
            fields: [],
            titleAttribute: 'summary',
        ));
    }
}
