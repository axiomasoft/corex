# Changelog

## Unreleased

- Add frozen `recurrence/1` DTO bundle (`RecurrenceSet`, `RecurrenceWindow`, `ExpansionLimits`, `ExpansionCursor`, `Occurrence`, `OccurrencePage`).
- Implement bounded `RRuleRecurrenceExpander` over qualified `rlanvin/php-rrule` v3.0.0 with DST gap/fold filtering, EXDATE/RDATE via `RSet`, cursor binding, and budget stops.
- Cover REC-01..REC-12 with unit and neutral consumer conformance tests.
