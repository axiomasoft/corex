# Company/contact API

Public commands in `Flex\Crm\Actions`:

```php
CreateCompany::handle(CompanyData $data): Company;
CreateContact::handle(ContactData $data): Contact;
AssociateContactCompany::handle(
    string|int $contactId,
    string|int $companyId,
    ?string $label = null,
    bool $isPrimary = false,
): ContactCompany;
```

All Data classes are final readonly. `CompanyData` takes name, legalName, domain,
inn, kpp, ogrn, phones, emails, address, bankDetails, sourceId, ownerId and accountLevel.
`ContactData` takes nameFirst/nameLast/nameMiddle, jobTitle, birthday (YYYY-MM-DD),
phones, emails, sourceId, ownerId and accountLevel. Neither takes actor, workspace ID,
connection or display/search payload. See their constructors for exact types/defaults.

```php
use Flex\Crm\Actions\{CreateCompany, CreateContact, AssociateContactCompany};
use Flex\Crm\Data\{CompanyData, ContactData};
use Flex\Crm\Models\{Company, Contact};

$company = app(CreateCompany::class)->handle(new CompanyData(
    name: 'Acme', domain: 'acme.test', inn: '7700000000',
));
$contact = app(CreateContact::class)->handle(new ContactData(
    nameFirst: 'Jane', nameLast: 'Doe',
    emails: [['value' => 'Jane@acme.test', 'type' => 'work', 'is_primary' => true]],
));
$link = app(AssociateContactCompany::class)->handle(
    contactId: $contact->id, companyId: $company->id, label: 'Decision maker', isPrimary: true,
);
$companies = Company::query()->orderBy('name')->get();
$card = Contact::query()->with('companies')->findOrFail($contact->id);
```

Before dispatch, the application binds `Flex\Crm\Context\Contracts\CrmExecutionContext` to
a scoped implementation. Its only method is `current(): ExecutionContext`; return the
authenticated current `TenantContext`, initialized connection name, verified `Actor`,
active principalId and explicit allowAccountLevel capability. Authenticate account
admission and non-user actor provenance using the application's actual mechanism on
every call. Never build this binding from a user-supplied actor/account/connection.
The package intentionally supplies no permissive fallback adapter.
Existing CoreX `TenantContextResolver`, `AccountConnectionResolver` and `ModuleRegistry`
must also be bound. Cloud consumers can use axiomasoft/corex-tenancy's existing bindings; no CRM
code depends on its implementation classes. Boxed constant context is unsupported here.

Mandatory admission checks run on every scoped read and again within mutation transactions:
the account must be active/non-boxed, context account/workspace must match CoreX's current
context, connection driver/host/port/database/user must match AccountConnectionResolver,
and PostgreSQL's actual current_database must match. CRM must be enabled. The principal
must be an active, non-soft-deleted account user; current workspace must exist and not be
soft-deleted, with active exact membership. Mutation checks hold shared user/workspace/
membership locks through commit. Losing membership makes subsequent reads/writes fail.
For user actors id equals principalId. Agent/api/workflow/import/system use a verified
run-as principal under the same checks. Actor storage and events carry B-13
`{source: user|agent|api|workflow|import|system, id, name}`; this is distinct from audit ACTOR.

Workspace reads see only the current workspace; NULL records are included only with
allowAccountLevel. A context without workspace requires allowAccountLevel and sees only
NULL records. Writes default to current workspace; `accountLevel: true` explicitly writes
NULL and requires that capability. Missing context always fails. Association requires
two visible, non-soft-deleted endpoints with exactly equal workspace IDs (including NULL).
Cross-workspace and NULL-to-workspace links reject. NULL source records may be referenced
from any admitted record scope; scoped sources must match the record scope and be active.
Owners are nullable; selected owners must be active account users and, while a workspace
context is selected, active members of that workspace. No roles or policy names are encoded.
Application authorization can narrow these rules. Creation provenance/workspace is immutable.
Validation runs again at the final INSERT/UPDATE attribute boundary, after saving/creating/
updating observers, including quiet saves. Late observers cannot forge provenance or move
scope. An observer veto makes the owning Action fail with ValidationException and rollback;
no mutation success event is emitted for an unsaved model.

Channels accept lists of `{value, type?, is_primary?}`; any supplied norm is recomputed.
Empty values, invalid shapes/types, duplicate normalized values and multiple primaries per
kind reject. International +country phones use libphonenumber-lite validation/E.164;
extensions and local-number guessing reject. Emails are trimmed, validated and lowercased.
No country/provider is inferred. Text components collapse Unicode whitespace; empty optional
components become NULL. Display name is last/first/middle joined with spaces and truncated
deterministically to 255 Unicode characters. If no name exists, use primary email then first
email, otherwise primary/first phone norm. If none exists, reject; never store an empty title.
Search is rebuilt on model saving using PostgreSQL to_tsvector, `russian` by B-13 default;
consumers may set `flex-crm.search_language` to another installed regconfig. Invalid config
causes transaction rollback. JSON channels defaults are []; duplicate INN is allowed.

Association takes a contact FOR UPDATE lock, then company FOR SHARE; the database's unique
pair and partial primary index remain authoritative for all concurrent writers. Duplicate
pair and second primary reject (no implicit replacement/update); label NULL is allowed.
Hard delete cascades associations; soft delete retains their storage and hides deleted
endpoints from normal relationships. Owner/source hard deletion SET NULL is separate from
soft deletion. All ID columns retain CoreX's uuid7/uuid/ulid/int profiles with compatible
prerequisites; UUIDv7 is the FlexCRM profile.

Extend through composition of Actions and existing manifest listeners:

```php
return ModuleManifest::make('acme/integration')->title('Integration')->tablePrefix('aci')
    ->listens([CompanyCreated::class => [CompanyListener::class]]);
```

The package introduces no plugin/workflow/pipeline system and no per-class interfaces.
Required additional consumer steps belong inside a composed Action on the same initialized
connection and outer transaction. Events CompanyCreated, ContactCreated and
ContactCompanyAssociated implement ShouldDispatchAfterCommit: callbacks run after the
outer successful commit and are discarded on rollback. In a successful composite they are
registered in command order. They are in-process notifications, not durable exactly-once
delivery: no outbox/crash recovery is claimed. Synchronous listeners run in Laravel registration
order; a listener exception propagates after data is committed and can prevent later listeners/
callbacks. Do not retry create blindly after that exception. Queue independent integrations
with ShouldQueue and their own retry/idempotency policy. Mandatory invariants never live in
after-commit listeners. Avoid listener ordering dependencies; compose dependent steps explicitly.
Direct query-builder/bulk mutations and scope removal are privileged maintenance escape
hatches, not supported extension points. Application HTTP/UI and its Policy remain outside
the domain package.

## Installed HTTP operations

The composing Laravel host can expose the CRM declarations through Operations under
`/api/v2`. Company create, list, show and contact association keep their existing
operation IDs. Hosts authorize `crm.company.update` and `crm.company.delete` separately.

- `PATCH /api/v2/companies/{companyId}` accepts `name`, `legal_name`, and `domain`.
  Omitted fields keep their values. Explicit null clears either optional field.
  The result carries the original company UUID and current normalized fields.
- `DELETE /api/v2/companies/{companyId}` returns `{"id":"…","deleted":true}`.
  It soft-deletes the company, retaining its UUID and association history.
  Fresh company show and list requests exclude the deleted row.

Both mutations require `Idempotency-Key` and current authorization. Use the
installed host's verified workspace and principal. The request body carries no
owner, workspace, or creator override.
