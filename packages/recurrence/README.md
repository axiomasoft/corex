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
