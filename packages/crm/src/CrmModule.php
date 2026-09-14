<?php

declare(strict_types=1);

namespace Flex\Crm;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Manifest;
use Flex\Crm\Api\CreateCompanyOperation;
use Flex\Crm\Api\CreateContactCompanyOperation;
use Flex\Crm\Api\CreateContactOperation;
use Flex\Crm\Api\CrmSchemas;
use Flex\Crm\Api\ListCompaniesOperation;
use Flex\Crm\Api\ListContactsOperation;
use Flex\Crm\Api\ShowCompanyOperation;
use Flex\Crm\Api\ShowContactOperation;
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
            Entity::make(Lead::class)->handle('crm.lead')->table('crm_leads')->label('Lead', 'Leads')->titleAttribute('title'),
            Entity::make(Activity::class)->handle('crm.activity')->table('crm_activities')->label('Activity', 'Activities')->titleAttribute('subject')->auditable(),
            Api::operation(
                id: 'crm.company.list', method: 'GET', path: '/companies', handler: ListCompaniesOperation::class, ability: 'crm.company.view',
                requestSchema: CrmSchemas::listInput(), responseSchema: CrmSchemas::listing(company: true),
                rules: ['limit' => ['sometimes', 'integer', 'min:1', 'max:100']],
            ),
            Api::operation(
                id: 'crm.company.show', method: 'GET', path: '/companies/{companyId}', handler: ShowCompanyOperation::class, ability: 'crm.company.view',
                requestSchema: ['type' => 'object', 'additionalProperties' => false], responseSchema: CrmSchemas::record(company: true),
            ),
            Api::operation(
                id: 'crm.company.create', method: 'POST', path: '/companies', handler: CreateCompanyOperation::class, ability: 'crm.company.create',
                requestSchema: CrmSchemas::record(company: true, request: true), responseSchema: CrmSchemas::record(company: true),
                rules: self::companyRules(), successStatus: 201,
            ),
            Api::operation(
                id: 'crm.contact.list', method: 'GET', path: '/contacts', handler: ListContactsOperation::class, ability: 'crm.contact.view',
                requestSchema: CrmSchemas::listInput(), responseSchema: CrmSchemas::listing(company: false),
                rules: ['limit' => ['sometimes', 'integer', 'min:1', 'max:100']],
            ),
            Api::operation(
                id: 'crm.contact.show', method: 'GET', path: '/contacts/{contactId}', handler: ShowContactOperation::class, ability: 'crm.contact.view',
                requestSchema: ['type' => 'object', 'additionalProperties' => false], responseSchema: CrmSchemas::record(company: false),
            ),
            Api::operation(
                id: 'crm.contact.create', method: 'POST', path: '/contacts', handler: CreateContactOperation::class, ability: 'crm.contact.create',
                requestSchema: CrmSchemas::record(company: false, request: true), responseSchema: CrmSchemas::record(company: false),
                rules: self::contactRules(), successStatus: 201,
            ),
            Api::operation(
                id: 'crm.contact.company.create', method: 'POST', path: '/contacts/{contactId}/companies', handler: CreateContactCompanyOperation::class, ability: 'crm.contact.associate',
                requestSchema: CrmSchemas::association(request: true), responseSchema: CrmSchemas::association(),
                rules: ['company_id' => ['required', 'string'], 'label' => ['nullable', 'string', 'max:64'], 'is_primary' => ['sometimes', 'boolean:strict']], successStatus: 201,
            ),
        ]);
    }

    /** @return array<string, list<string>> */
    private static function companyRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:190'],
            'inn' => ['nullable', 'string', 'max:12'],
            'kpp' => ['nullable', 'string', 'max:9'],
            'ogrn' => ['nullable', 'string', 'max:15'],
            'phones' => ['sometimes', 'array'],
            'phones.*.value' => ['required_with:phones', 'string'],
            'phones.*.type' => ['nullable', 'string', 'max:32'],
            'phones.*.is_primary' => ['sometimes', 'boolean:strict'],
            'emails' => ['sometimes', 'array'],
            'emails.*.value' => ['required_with:emails', 'string'],
            'emails.*.type' => ['nullable', 'string', 'max:32'],
            'emails.*.is_primary' => ['sometimes', 'boolean:strict'],
            'address' => ['nullable', 'array'],
            'bank_details' => ['nullable', 'array'],
            'source_id' => ['nullable'],
            'owner_id' => ['nullable'],
            'account_level' => ['sometimes', 'boolean:strict'],
        ];
    }

    /** @return array<string, list<string>> */
    private static function contactRules(): array
    {
        return [
            'name_first' => ['nullable', 'string', 'max:120'],
            'name_last' => ['nullable', 'string', 'max:120'],
            'name_middle' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:190'],
            'birthday' => ['nullable', 'date_format:Y-m-d'],
            'phones' => ['sometimes', 'array'],
            'phones.*.value' => ['required_with:phones', 'string'],
            'phones.*.type' => ['nullable', 'string', 'max:32'],
            'phones.*.is_primary' => ['sometimes', 'boolean:strict'],
            'emails' => ['sometimes', 'array'],
            'emails.*.value' => ['required_with:emails', 'string'],
            'emails.*.type' => ['nullable', 'string', 'max:32'],
            'emails.*.is_primary' => ['sometimes', 'boolean:strict'],
            'source_id' => ['nullable'],
            'owner_id' => ['nullable'],
            'account_level' => ['sometimes', 'boolean:strict'],
        ];
    }
}
