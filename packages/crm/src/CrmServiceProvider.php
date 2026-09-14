<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use Flex\Crm\Models\Activity;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Lead;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Models\Stage;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register entities so they enter corex/modules's morph map (built in
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
