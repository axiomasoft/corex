<?php

declare(strict_types=1);

namespace Flex\Crm;

use Flex\Crm\Activity\Models\Activity;
use Flex\Crm\ApiShared\CrmSchemas;
use Flex\Crm\Company\Api\CreateCompanyOperation;
use Flex\Crm\Company\Api\ListCompaniesOperation;
use Flex\Crm\Company\Api\ShowCompanyOperation;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Api\CreateContactCompanyOperation;
use Flex\Crm\Contact\Api\CreateContactOperation;
use Flex\Crm\Contact\Api\ListContactsOperation;
use Flex\Crm\Contact\Api\ShowContactOperation;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Pipeline;
use Flex\Crm\Deal\Models\Stage;
use Flex\Crm\Internal\CrmSchemaSets;
use Flex\Crm\Lead\Models\Lead;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Extensions\Concerns\Persistence\OwnsSchema;
use Packman\Extensions\Contracts\Persistence\WithSchema;
use Packman\Modules\Contracts\Definition\ModuleDefinition;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Enums\Entities\Capability;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CrmModule extends ModuleDefinition implements WithSchema
{
    use OwnsSchema;

    #[Override]
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('flex/crm')->title('CRM')->tablePrefix($this->tablePrefix())->extends([
            Entity::make(Pipeline::class)->handle('crm.pipeline')->table('crm_pipelines')->label(singular: 'Pipeline', plural: 'Pipelines')->titleAttribute('name')
                ->capabilities(Capability::WorkspaceScoped),
            Entity::make(Stage::class)->handle('crm.stage')->table('crm_stages')->label(singular: 'Stage', plural: 'Stages')->titleAttribute('name'),
            Entity::make(Deal::class)->handle('crm.deal')->table('crm_deals')->label(singular: 'Deal', plural: 'Deals')->titleAttribute('title')->auditable()
                ->capabilities(Capability::Ownable, Capability::WorkspaceScoped),
            Entity::make(Company::class)->handle('crm.company')->table('crm_companies')->label(singular: 'Company', plural: 'Companies')->titleAttribute('name')
                ->capabilities(Capability::Ownable, Capability::WorkspaceScoped),
            Entity::make(Contact::class)->handle('crm.contact')->table('crm_contacts')->label(singular: 'Contact', plural: 'Contacts')->titleAttribute('display_name')
                ->capabilities(Capability::Ownable, Capability::WorkspaceScoped),
            Entity::make(Lead::class)->handle('crm.lead')->table('crm_leads')->label(singular: 'Lead', plural: 'Leads')->titleAttribute('title')
                ->capabilities(Capability::Ownable, Capability::WorkspaceScoped),
            Entity::make(Activity::class)->handle('crm.activity')->table('crm_activities')->label(singular: 'Activity', plural: 'Activities')->titleAttribute('subject')->auditable()
                ->capabilities(Capability::Ownable, Capability::WorkspaceScoped),
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

    public function tablePrefix(): string
    {
        return 'crm';
    }

    /** @return list<class-string> */
    public function schemaSetProviders(): array
    {
        return [CrmSchemaSets::class];
    }
}
