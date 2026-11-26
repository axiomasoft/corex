# flex/crm

The CoreX CRM module manages sales pipelines, companies, contacts and leads. An activity timeline links calls, meetings and emails to CRM records. CRM models generate UUIDv7 primary keys. Deals store money in minor units through `MoneyCast`, and the entity registry supplies the morph map. Each account has its own database. Company, contact and association queries also enforce the current workspace.

See the [company and contact API](COMPANY_CONTACT_API.md) for relationships, context binding and extension points. The [upgrade guide](UPGRADE.md) describes breaking changes and historical data conversion.

## Domain operations

- A `Pipeline` orders its `Stage` records and tracks won and lost stages. `CreateDeal` and `MoveDealToStage` update deals. A `Deal` exposes its monetary amount as `Brick\Money\Money` and uses `DealStatus` for its status.
- `CreateCompany`, `CreateContact` and `CreateLead` create CRM records. `ConvertLead` creates a contact and can also create a company and a deal. `AssociateContactCompany` links a contact to a company.
- `LogActivity` records a typed `ActivityType` against a supported CRM subject. The module registers pipeline, stage, deal, company, contact, lead and activity entities through `EntityRegistry`.
- Actions run inside transactions. `DealCreated`, `DealMovedToStage`, `CompanyCreated`, `ContactCreated`, `ContactCompanyAssociated`, `LeadCreated`, `LeadConverted` and `ActivityLogged` dispatch after the outer transaction commits.

These actions and models belong to the CRM implementation. The public PHP manifest lists the context contract and integration types. See the [API manifest](resources/contracts/php-api.json).

## Installation and lifecycle

Install `flex/crm` with Composer on Laravel 13. Applications can install `axiomasoft/corex-access` for permissions and record filtering. For HTTP dispatch of the declared operations, install `axiomasoft/corex-operations` and register its `OperationsServiceProvider`.

1. Prepare the tenant connection. Install the host tables `users`, `wsp_workspaces` and `wsp_workspace_members`, using `axiomasoft/corex-tenancy` or a compatible host schema. CRM expects UUID user and workspace keys and references these tables through foreign keys.
2. Run `php artisan modules:compile`. Composer discovers `CrmModule` as a `packman-module`. Enable `flex/crm` in the target tenant through `ModuleLifecycle::enable()`. For tenant template preparation, use `php artisan packman:schema:migrate --scope=tenant --modules --database=tenant`.
3. Inspect the migration journal with `php artisan packman:schema:status`. Disabling the module preserves its data. Enabling it again is idempotent.

For installations with CRM tables predating the module manifest, back up the account before migrating. Reconcile historical data and ownership claims before using lifecycle operations. Company and contact storage includes JSON channels and PostgreSQL GIN indexes. Historical `custom_fields` require a separate migration decision.

## Execution context

Bind `Flex\Crm\Context\Contracts\CrmExecutionContext` to the host implementation. Each `current()` call verifies the actor and account admission and returns `Flex\Crm\Context\Data\ExecutionContext` with the tenant context, connection name, actor and current principal ID. Account-wide access requires an explicit capability. Revoke access by changing the user's active status or membership. Subsequent CRM operations check the current rows, including changes made earlier in the same transaction.

## Tests

The package suite is under `products/flex-crm/tests`. From the monorepo root run the following with the package dependencies installed. Point the PostgreSQL suite to an isolated test database.

```bash
composer --working-dir=products/flex-crm test
composer --working-dir=products/flex-crm test:pg
composer --working-dir=products/flex-crm analyse
```
