# Changelog

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased

- Add frozen `recurrence/1` DTO bundle (`RecurrenceSet`, `RecurrenceWindow`, `ExpansionLimits`, `ExpansionCursor`, `Occurrence`, `OccurrencePage`).
- Implement bounded `RRuleRecurrenceExpander` over qualified `rlanvin/php-rrule` v3.0.0 with DST gap/fold filtering, EXDATE/RDATE via `RSet`, cursor binding, and budget stops.
- Cover REC-01..REC-12 with unit and neutral consumer conformance tests.
