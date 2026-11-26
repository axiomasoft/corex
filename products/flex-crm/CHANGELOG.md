# Changelog

## Unreleased

- Recheck the active user, workspace and membership at each admission. This observes withdrawals made by the host inside the current transaction. Workspace admissions read and lock the three rows in one joined statement on the successful write path.

## Unreleased — Packman governance

- Declare product ownership and lifecycle metadata in `extra.packman`, export the module/provider SPI, and explicitly classify implementation types as Internal. Compose the module schema through Packman `WithSchema` / `OwnsSchema` and use shared configuration/error contracts.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — kernel invocation contract (audit 2026-10-08 P-02)

- CRM operation handlers implement `CoreX\Invocation\Contracts\OperationHandler` from the kernel. `axiomasoft/corex-operations` moves from `require` to `require-dev` + `suggest`: install it in the host to serve the CRM API; the product itself no longer depends on the operations runtime (enforced by package-graph-gate).

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — baseline v1

- The `tenant` schema set started at baseline v1. It replaced the unreleased upgrade sequence so new installations use the final schema.

## Unreleased — deterministic UUID schema (P08.2)

- Models use Laravel HasUuids (UUIDv7); migrations use literal table names and UUID columns without configuration or model lookups.

All notable changes to `flex/crm` are recorded here following [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Before 1.0, minor releases may introduce breaking changes to public contracts (D17).

## [Unreleased]

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

### Changed

- CRM entities enter the runtime registry and morph map through the compiled `CrmModule` manifest; the provider no longer depends on a Testing registry.

- Breaking (PLAN3.P02–P04, pre-1.0): CRM stays a `flex/crm` product with one `Flex\Crm` PSR-4 root; lead/contact/company operations use typed request/result DTOs and explicit Api/Spi declarations. CRM model persistence is Internal, its errors use package-owned codes, and kernel dependencies use the `axiomasoft` Composer vendor.

- **Breaking (next MINOR >=0.3.0, pre-1.0):** additive full enabled CRM storage parity,
  canonical sales enums/fields/relations, lost reasons and activity targets; explicit
  CreateLead and LogActivity named-argument migration. No guessed legacy status,
  author, metadata or contact identity conversion. See UPGRADE.md.
- Existing seven company/contact operations publish typed input/output schemas;
  boolean inputs reject numeric/string lookalikes instead of reaching strict DTOs.
- Sales Actions honor observer vetoes transactionally; nullable deal amount preserves
  mandatory currency, and conversion pivot IDs use the owning configured-ID seam.

- **Breaking (next MINOR, pre-1.0):** approved company/contact fields, B-13 `{source,id,name}`
  provenance, deterministic display/channels/search and typed contact/company M2M replace
  legacy scalar fields and single-company relationship. Create Actions now accept CompanyData/
  ContactData; CompanyCreated/ContactCreated include Actor. ConvertLead is adapted explicitly.
- New public `Contracts/CrmExecutionContext::current(): ExecutionContext` requires authenticated
  execution and CoreX account context/connection bindings. Reads fail closed; nullable account
  records require explicit capability, workspace membership is rechecked and locked on writes.
- Additive parity migration verifies identity/workspace prerequisites, permits duplicate INN,
  and enforces FK/pair/primary/index contracts. Populated legacy upgrade rejects before changing
  data; no invented actors or silent metadata truncation. See UPGRADE.md.
- Final insert/update attribute guards prevent late/quiet Eloquent observers from bypassing
  admission/provenance invariants; owning Actions roll back observer veto without success events.

- **Breaking:** Renamed the package from `corex/crm` to `flex/crm` and its namespace from `CoreX\Crm\` to `Flex\Crm\` under the layer policy (D70/D72). Update consumer `require` and `use` declarations (P2.0).
