# P5.4 workflow binding test evidence

Observed on 2026-09-16 in `/home/vostrikov/projects/packages/corex`.

`vendor/bin/pint packages/workflow` passed.

`composer --working-dir=packages/workflow analyse` passed with zero PHPStan errors.

`composer --working-dir=packages/workflow test` passed: 4 tests, 7 assertions. The suite verifies
durable trigger capture, exact replay/conflict handling, typed signal/transition delegation and
expiry rejection. The test adapter is a local contract double; durable engine/FSM upstream behavior
remains the independently qualified P5.1 receipt.
