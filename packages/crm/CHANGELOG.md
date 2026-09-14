# Changelog

Все заметные изменения `flex/crm` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

### Changed

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

- **Breaking:** пакет и namespace переименованы `corex/crm`/`CoreX\Crm\` → `flex/crm`/`Flex\Crm\`
  (доктрина слоёв, D70/D72). Consumer-код обновляет `use`/`require` на новые имена (P2.0).
