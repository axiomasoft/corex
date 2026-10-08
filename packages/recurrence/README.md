# axiomasoft/corex-recurrence

`axiomasoft/corex-recurrence` exposes a bounded, typed recurrence expansion port over the qualified `rlanvin/php-rrule` v3.0.0 source. It has no Laravel, database, task, network, or scheduler dependency.

## Port

```php
RecurrenceExpander::expand(
    RecurrenceSet $set,
    RecurrenceWindow $window,
    ExpansionLimits $limits,
    ?ExpansionCursor $after = null,
): OccurrencePage
```

Construct a `RecurrenceSet` with schema `recurrence/1`, immutable revision, civil `DateStart` (`DATE`, `TZID`, or `UTC`), optional `RRULE`, `RDATE`/`EXDATE` lists, overrides, and pinned `tzdbVersion`. Expand through `RRuleRecurrenceExpander` in an explicit half-open window with bounded limits.

Unsupported profile features return `OccurrencePage` status `unsupported` or `invalid`; traversal budgets return `partial` with a resumable cursor.

## Validation

```bash
composer test
composer analyse
composer lint:check
```

## Installation

Install with `composer require axiomasoft/corex-recurrence` through the configured Composer channel. The package needs no Laravel provider, database migration or background scheduler. Supply a recurrence set, explicit half-open expansion window and traversal limits to the public expansion port.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

Each expansion call has its own emission and new-candidate budgets. Cursor counters
remain absolute traversal positions. Seeking the saved prefix is bounded by the
same wall-clock and memory limits; budget exhaustion preserves the previous position
and never reports completion for unprocessed candidates. Large offsets may require
more wall time to replay the library iterator. No separate unbounded lookahead runs.
