# Changelog

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased

- Add frozen `recurrence/1` DTO bundle (`RecurrenceSet`, `RecurrenceWindow`, `ExpansionLimits`, `ExpansionCursor`, `Occurrence`, `OccurrencePage`).
- Implement bounded `RRuleRecurrenceExpander` over qualified `rlanvin/php-rrule` v3.0.0 with DST gap/fold filtering, EXDATE/RDATE via `RSet`, cursor binding, and budget stops.
- Cover REC-01..REC-12 with unit and neutral consumer conformance tests.
