<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Manifest;
use Flex\Crm\Models\Activity;
use Flex\Crm\Models\Company;
use Flex\Crm\Models\Contact;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Lead;
use Flex\Crm\Models\Pipeline;
use Flex\Crm\Models\Stage;
use Override;

final class CrmModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('flex/crm')->title('CRM')->tablePrefix('crm')->extends([
            Entity::make(Pipeline::class)->handle('crm.pipeline')->table('crm_pipelines')->label('Pipeline', 'Pipelines')->titleAttribute('name'),
            Entity::make(Stage::class)->handle('crm.stage')->table('crm_stages')->label('Stage', 'Stages')->titleAttribute('name'),
            Entity::make(Deal::class)->handle('crm.deal')->table('crm_deals')->label('Deal', 'Deals')->titleAttribute('title')->auditable(),
            Entity::make(Company::class)->handle('crm.company')->table('crm_companies')->label('Company', 'Companies')->titleAttribute('name'),
            Entity::make(Contact::class)->handle('crm.contact')->table('crm_contacts')->label('Contact', 'Contacts')->titleAttribute('display_name'),
            Entity::make(Lead::class)->handle('crm.lead')->table('crm_leads')->label('Lead', 'Leads')->titleAttribute('name'),
            Entity::make(Activity::class)->handle('crm.activity')->table('crm_activities')->label('Activity', 'Activities')->titleAttribute('summary')->auditable(),
        ]);
    }
}
