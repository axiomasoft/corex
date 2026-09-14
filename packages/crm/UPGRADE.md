# Company/contact parity upgrade

This is a pre-1.0 breaking MINOR candidate after monorepo v0.2.0. No release tag is implied.
Back up the account database before a separately authorized installation/upgrade.

Fresh or empty legacy installations: initialize the existing `users`, `wsp_workspaces`
and `wsp_workspace_members` owning schema with matching configured IDs, compile the
installed modules, then enable `flex/crm` in that account connection. The additive
`2026_09_14_000002_company_contact_parity` migration replaces only empty historical
company/contact tables and creates sources and the typed association. Earlier migration
files remain unchanged. PostgreSQL 15+ is required; FlexCRM uses `corex.ids.strategy=uuid7`.

Populated legacy installations are explicitly unsupported by automatic in-place upgrade.
The migration throws `CRM_PARITY_UPGRADE_REQUIRES_EXPORT` before changing CRM tables.
Legacy storage has no reliable `created_by`; it also contains `custom_fields` and
`merged_into_id`, which cannot be silently discarded or added to the approved target.
The safe conversion procedure is an offline export into a new isolated account database:
retain a read-only source backup; obtain a provenance mapping for every source row;
map `website` to domain only after extracting/validating the hostname, name components
to the new names, scalar channels to normalized lists and `company_id` to a primary
association; retain source IDs only under the same ID profile; export custom/merge
metadata separately until its owning storage is approved. Validate tax-ID widths, all
references, complete row counts and provenance before any separately authorized cutover.
Do not label unknown historical authors as system/import or overwrite the source database.
A production backfill/cutover tool is outside this fresh first-consumer slice.

Replace `CreateCompany::handle(...)` with `handle(CompanyData $data)` and
`CreateContact::handle(...)` with `handle(ContactData $data)`. There are no silent
legacy aliases. Replace `Contact::company()` / `company_id` with `companies()` and
`AssociateContactCompany`; `Company::contacts()` is now a typed BelongsToMany.
`CompanyCreated` and `ContactCreated` now also carry the verified `Actor`.
`ConvertLead` has been adapted to the DTOs and a primary association in its original
transaction; its authenticated execution context is mandatory. Malformed legacy phones,
invalid owners or absent context reject and roll back conversion explicitly.

Disable preserves data. Re-enable is idempotent. Parity `down()` refuses a destructive
loss of target data: restore a verified backup only in an authorized maintenance operation.
Do not use module purge as an upgrade or rollback mechanism.

The module still contains historical pipeline/stage/deal/lead/activity migrations.
Their complete enable path is tested, but their DDL is not the approved FlexCRM sales
schema. Full-target schema admission needs its owning continuation before app installation;
this company/contact change does not approve those additional shapes.
