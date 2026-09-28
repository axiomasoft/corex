# pplx-02 — Pest 5 и PHPUnit 13: несовместимые изменения

- **Дата:** 2026-09-28
- **Инструмент:** perplexity-web `search` (два запроса)

## Запрос 1

«Pest PHP v5 upgrade guide from Pest 4: breaking changes, PHPUnit 13 requirement, removed or changed APIs (arch presets,
dataset, expectations, --parallel, coverage). Also PHPUnit 13 breaking changes vs PHPUnit 12.»

### Ответ (дословно)

"Area","What changes"
"PHP","Pest 5 requires PHP 8.4 or later."
"PHPUnit","Pest 5 is built on PHPUnit 13, so PHPUnit’s own changes may affect your suite."
"Composer/plugins","Change pestphp/pest from ^4.0 to ^5.0; update Pest-maintained plugins to ^5.0 too."
"Arch presets, datasets, expectations","The Pest 5 upgrade guide lists no removal or migration for these APIs. Pest 5’s release announcement still documents the expect() API and new expectations; the current docs still describe datasets. pestphp+1"
"'--parallel","Still supported. The Pest 5 announcement itself demonstrates pest --parallel --tia; the CLI reference lists --parallel for parallel execution. pestphp+1"
"Coverage","The Pest 5 announcement says Tia’s first run needs PCOV or Xdebug to record its dependency baseline, and cached test replays preserve coverage data. This is a Tia-specific requirement, not a general removal of coverage support. pestphp"

## Запрос 2

«PHPUnit 13 release notes: list of backward incompatible changes and removals compared to PHPUnit 12 (removed assertions,
mock/stub API changes, configuration XML changes, withConsecutive, any() on stubs)»

### Ответ (дословно)

PHPUnit 13.0 requires PHP 8.4 or later. The main migration rule is to clear PHPUnit 12.5 deprecation warnings before upgrading: PHPUnit 13 hard-deprecates some APIs that still work, and removes APIs that were already hard-deprecated in PHPUnit 12.

## Removed in PHPUnit 13

- Assertions and constraints: Assert::isType(), assertContainsOnly(), assertNotContainsOnly(), and containsOnly() are removed. Replace them with type-specific assertions or constraints, such as assertContainsOnlyInstancesOf() or containsOnlyInt(), according to the case.
- Test attributes and coverage: #[RunClassInSeparateProcess] is removed; use #[RunTestsInSeparateProcesses]. PHPUnit also no longer supports #[CoversNothing] on an individual test method.
- Other removed APIs/options: Configuration::includeTestSuite() and excludeTestSuite(), --dont-report-useless-tests, and the hook event value object’s testClassName() method for test-method hooks are removed. PHPUnit 13 also drops support for PHP 8.3 and for version-constraint strings without an explicit comparison operator.

## Mocks, stubs, and withConsecutive()

- withConsecutive() is not a PHPUnit 13 removal: it was removed in PHPUnit 10, so it is already absent from PHPUnit 12. PHPUnit 13 adds withParameterSetsInOrder([...]) and withParameterSetsInAnyOrder([...]) as alternatives for checking multiple argument sets across calls.
- with() on a stub stops working: using with() on an object from createStub() was deprecated starting in PHPUnit 12.5.11 and no longer works in PHPUnit 13. Use createMock() when you need argument verification; use createStub() to configure return values without verifying calls.
- any() is deprecated, not removed, in PHPUnit 13: $this->any() is hard-deprecated, so tests still run but emit a deprecation. PHPUnit 12.5 already deprecated it; it is scheduled for removal in PHPUnit 14. Replace it with a specific expectation such as once() or exactly($n) if the call matters, or use a stub if it does not.
- New, optional stricter behavior: PHPUnit 13 adds seal() for test doubles and the requireSealedMockObjects XML setting. Sealing prevents further configuration after setup; it is additive rather than an automatic breaking change.

## XML configuration

The PHPUnit 13.0 removal list does not identify a broad set of XML configuration attributes or elements removed from PHPUnit 12. The XML-related removed API in that list is the PHP-level Configuration::includeTestSuite() / excludeTestSuite() methods; the release announcement highlights requireSealedMockObjects as a new XML setting.
Check later 13.x changes separately from the 13.0 upgrade list: for example, PHPUnit 13.2 deprecated the ambiguous executionOrder="duration" and "size" values in favor of explicit ascending/descending values. PHPUnit 13.3 then deprecated cacheResult in favor of recordTestRunHistory. These are deprecations in later 13.x releases, not PHPUnit 12-to-13 removals.

## Practical migration checklist

- Run the full suite on PHPUnit 12.5 and eliminate deprecation warnings before upgrading.
- Replace isType(), containsOnly(), and assertContainsOnly*() usages with the corresponding type-specific APIs.
- Ensure argument checks use mocks, not stubs; replace any() with a meaningful call-count expectation or a stub.
- Remove any reliance on the deleted coverage/process attributes or PHPUnit configuration methods.
- Set the test environment to PHP 8.4 or newer.

## Источники

1. [phpunit](https://phpunit.de/announcements/phpunit-13.html)
2. [newreleases](https://newreleases.io/project/packagist/phpunit/phpunit/release/13.0.0)
3. [docs.phpunit](https://docs.phpunit.de/en/12.5/test-doubles.html)
