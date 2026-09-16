# P4.5 notification channels — execution receipt

Status: done with deviations.

The package now provides the frozen `RecipientResolver` and
`PreferenceRepository` seams, Settings-backed per-recipient preferences with
optimistic revision, a registered in-app channel, and a delivery coordinator
that rechecks current policy and recipient resolution before driver admission.
An unavailable driver or target is durably recorded as skipped; an uncertain
worker exception is recorded as unknown.

The package lint, PHPStan, SQLite suite (12 tests, 41 assertions), and isolated
PostgreSQL lane (2 tests, 2 assertions) passed. No Laravel mail adapter was registered:
the plan requires a matching G-DEPENDENCY receipt before adding the optional
mail dependency/provider surface. The in-app channel is the delivered boxed
profile.

The remaining deviation is only the unavailable coverage driver/root-wide
validation; coverage thresholds were not changed.
