# Company/contact API

The host installs the CRM module and Operations to expose its company and contact endpoints. The supported PHP integration types are listed in the [API manifest](resources/contracts/php-api.json). Bind the execution context before accepting requests.

## Create and associate records

Send the following JSON to `POST /api/v2/companies` in an authorized account and workspace:

```json
{"name": "Acme", "domain": "acme.test", "inn": "7700000000"}
```

Send the contact to `POST /api/v2/contacts`:

```json
{"name_first": "Jane", "name_last": "Doe", "emails": [{"value": "Jane@acme.test", "type": "work", "is_primary": true}]}
```

Use the resulting contact ID in `POST /api/v2/contacts/{contactId}/companies`. Supply the UUID returned by the company operation as `company_id`, and optionally `label` and `is_primary`.

## Host execution context

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
run-as principal under the same checks. Actor storage and events carry
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
Search is rebuilt on model saving using PostgreSQL to_tsvector, with `russian` as the default configuration;
consumers may set `flex-crm.search_language` to another installed regconfig. Invalid config
causes transaction rollback. JSON channels defaults are []; duplicate INN is allowed.

Association takes a contact FOR UPDATE lock, then company FOR SHARE; the database's unique
pair and partial primary index remain authoritative for all concurrent writers. Duplicate
pair and second primary reject (no implicit replacement/update); label NULL is allowed.
Hard delete cascades associations; soft delete retains their storage and hides deleted
endpoints from normal relationships. Owner/source hard deletion SET NULL is separate from
soft deletion. FlexCRM models generate UUIDv7 primary keys. CRM keys and their references use UUID columns.

## Transaction and event behavior

`CompanyCreated`, `ContactCreated` and `ContactCompanyAssociated` dispatch after the outer transaction commits, in registration order. A rollback discards pending callbacks. These in-process events have no durable delivery guarantee. A synchronous listener exception propagates after commit and can interrupt subsequent callbacks. Check the committed record before retrying a failed request. Queue independent integrations with their own retry and idempotency policy.

Callers use the installed Operations declarations for writes. Direct query-builder updates bypass the domain checks. The host owns its HTTP authorization policy.

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
