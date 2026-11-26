# Full CRM storage parity upgrade

## Installing the current schema

Back up the account database before an upgrade. The current `flex/crm:tenant` schema set ships versioned files from the package. Provision `users`, `wsp_workspaces` and `wsp_workspace_members` with UUID keys on the tenant PostgreSQL connection, compile the installed modules and enable `flex/crm`. The kernel requires PostgreSQL 16 or later. FlexCRM generates UUIDv7 primary keys and stores them in UUID columns.

An installation already using the published baseline can run `php artisan packman:schema:migrate --scope=tenant --modules --database=tenant` to apply outstanding module migrations to the selected tenant connection. Verify its migration journal with `packman:schema:status`. The current package does not ship the unreleased September migrations named `company_contact_parity` and `full_storage_parity`.

## Converting a pre-baseline database

The current baseline expects a fresh schema. For populated, pre-baseline tables, prepare a separate offline conversion to a new account database. Historical records can lack reliable `created_by` values. Export `custom_fields` and `merged_into_id` separately until their destination is agreed.

Keep a read-only backup and obtain a provenance mapping for every row. Extract and validate a hostname before mapping `website` to `domain`. Map contact name components to their current fields, convert scalar channels to normalized lists and convert `company_id` to a primary association. Retain source IDs only if they are valid UUIDs and all their references can be preserved. Verify row counts, references and actor provenance before switching the host connection.

Replace `CreateCompany::handle(...)` with `handle(CompanyData $data)` and
`CreateContact::handle(...)` with `handle(ContactData $data)`. There are no silent
legacy aliases. Replace `Contact::company()` / `company_id` with `companies()` and
`AssociateContactCompany`; `Company::contacts()` is now a typed BelongsToMany.
`CompanyCreated` and `ContactCreated` now also carry the verified `Actor`.
`ConvertLead` has been adapted to the DTOs and a primary association in its original
transaction; its authenticated execution context is mandatory. Malformed legacy phones,
invalid owners or absent context reject and roll back conversion explicitly.

Disabling the module preserves account data. Re-enabling it is idempotent. Restore a verified backup to roll back a conversion.

Sales API compatibility (next pre-1.0 MINOR, not a release): `CreateLead` now requires
`title`, with explicit optional `contactName`, `email`, `phone`, `companyName`, `ownerId`
and `sourceId`. Lead title is never interpreted as a human name. LeadStatus is
`Open/Converted/Junk`; no aliases for New/Contacted/Qualified/Unqualified exist.
Pipelines require `entity_type`, `code` and localized `name`; stages require `code`,
localized `name` and `semantic=open/won/lost`, not `is_won/is_lost` flags. Deal amount
is nullable minor units with mandatory currency (Money values remain supported);
`Deal::contact()`/`contact_id` become `contacts()`. ActivityType contains only
Call/Email/Meeting: Note and Task have no implied conversion. Activity target reads
use `targets()` or entity `activities()`, while activity `subject` is now text.
`LogActivity` retains summary-to-subject, but the named `userId` argument is replaced
by `ownerId`; this is ownership, NOT historical authorship. Legacy `metadata` is
explicitly rejected, not discarded. All sales writes require trusted context and
preserve observer veto rollback and after-commit events. Product adapters must be
updated explicitly before a separately authorized application installation.
