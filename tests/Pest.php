<?php

declare(strict_types=1);

use CoreX\Tasks\Testing\TasksTestCase;
use CoreX\TasksWorkflow\Tests\PgTestCase;
use CoreX\Testing\CoreXTestCase;
use CoreX\Tests\TestCase;
use Flex\Crm\Testing\CrmTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(CoreXTestCase::class)->in('../packages/core/tests');
uses(TestCase::class)->in('../packages/modules/tests');
uses(CrmTestCase::class, RefreshDatabase::class)->in('Crm');
uses(TasksTestCase::class, RefreshDatabase::class)->in('Tasks');
uses(CoreX\TasksWorkflow\Tests\TestCase::class, RefreshDatabase::class)->in('../packages/tasks-workflow/tests/Feature');
uses(PgTestCase::class)->in('../packages/tasks-workflow/tests/Pg');
uses(CoreX\Intl\Tests\TestCase::class, RefreshDatabase::class)->in('../packages/intl/tests/Locale', '../packages/intl/tests/Money', '../packages/intl/tests/Reference');
uses(CoreX\Intl\Tests\PgTestCase::class)->in('../packages/intl/tests/Pg');
