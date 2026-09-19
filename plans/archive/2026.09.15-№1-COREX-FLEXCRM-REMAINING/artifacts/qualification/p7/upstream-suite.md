# rlanvin/php-rrule v3.0.0 upstream qualification

- Source: `https://github.com/rlanvin/php-rrule`, tag `v3.0.0`, commit `93a083db12dcb6f58e4840392a22e158ce96f1ff`.
- Clean checkout: `git clone --depth 1 --branch v3.0.0`.
- Dependency install: `composer install --no-interaction --prefer-dist` exited 0.
- Suite: `vendor/bin/phpunit` exited 0: 628 tests, 4430 assertions, 1 incomplete (`RSetTest::testAddExRule` has upstream `TODO!`).
- Core adapter seam exercised locally: `RRule::getOccurrencesBetween()` supports an explicit start/end/limit; replay is bounded by the CoreX cursor limit.

The upstream incomplete test is retained as source provenance. It is outside the adopted `RRULE`-only adapter profile: CoreX rejects EXRULE rather than claiming its support.
