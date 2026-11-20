<?php

declare(strict_types=1);

use CoreX\Intl\Tests\PgTestCase;
use CoreX\Panels\Tests\SavedViewsTestCase;
use CoreX\Testing\CoreXTestCase;
use CoreX\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/Support/LocalWorkContexts.php';
require_once __DIR__.'/Support/WorkScopeFixture.php';

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(CoreXTestCase::class)->in('../packages/core/tests/Unit', '../packages/core/tests/Storage', '../packages/core/tests/Views', '../packages/core/tests/Feature', '../packages/core/tests/Pg');
uses(TestCase::class)->in('../packages/core/tests/Modules');
uses(CoreX\RecurringTasks\Tests\TestCase::class, RefreshDatabase::class)->in('../packages/recurring-tasks/tests/Feature');
uses(CoreX\Intl\Tests\TestCase::class, RefreshDatabase::class)->in('../packages/intl/tests/Locale', '../packages/intl/tests/Money', '../packages/intl/tests/Reference');
uses(PgTestCase::class)->group('pg')->in('../packages/intl/tests/Pg');

uses(SavedViewsTestCase::class)->in('../packages/panels/tests/SavedViews');
