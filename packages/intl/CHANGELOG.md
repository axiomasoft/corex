# Changelog

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## [Unreleased]

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

### Added

- Frozen locale, money, and geography contracts with scoped `IntlServiceProvider`
- SQL-backed rate snapshots and reference dataset publication with CAS pointers
- Licensed `boxes` and `compact` fixture providers for offline conformance tests
- `AddressSuggestProvider` port with bounded `AddressQuery`, `SuggestionLimits`, and fixture/configured drivers
