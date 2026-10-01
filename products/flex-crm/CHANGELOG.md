# Changelog

Все заметные изменения `flex/crm` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

### Changed

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

- **Breaking:** пакет и namespace переименованы `corex/crm`/`CoreX\Crm\` → `flex/crm`/`Flex\Crm\`
  (доктрина слоёв, D70/D72). Consumer-код обновляет `use`/`require` на новые имена (P2.0).
