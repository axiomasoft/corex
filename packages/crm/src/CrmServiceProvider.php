<?php

declare(strict_types=1);

namespace CoreX\Crm;

use CoreX\Contracts\EntityRegistry;
use CoreX\Crm\Models\Activity;
use CoreX\Crm\Models\Company;
use CoreX\Crm\Models\Contact;
use CoreX\Crm\Models\Deal;
use CoreX\Crm\Models\Lead;
use CoreX\Crm\Models\Pipeline;
use CoreX\Crm\Models\Stage;
use Illuminate\Support\ServiceProvider;

final class CrmServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register entities so they enter corex/core's morph map (built in the
        // app.booted callback, after every module provider has booted).
        $registry = $this->app->make(EntityRegistry::class);
        $registry->register(Pipeline::class, ['alias' => 'crm_pipeline']);
        $registry->register(Stage::class, ['alias' => 'crm_stage']);
        $registry->register(Deal::class, ['alias' => 'crm_deal', 'auditable' => true]);
        $registry->register(Company::class, ['alias' => 'crm_company']);
        $registry->register(Contact::class, ['alias' => 'crm_contact']);
        $registry->register(Lead::class, ['alias' => 'crm_lead']);
        $registry->register(Activity::class, ['alias' => 'crm_activity', 'auditable' => true]);
    }
}
