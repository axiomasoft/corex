<?php

declare(strict_types=1);

use CoreX\Access\Tests\Authorization\PolicyTestCase;
use CoreX\Access\Tests\PgTestCase;
use CoreX\Access\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once dirname(__DIR__, 3).'/tests/Support/LocalWorkContexts.php';
require_once dirname(__DIR__, 3).'/tests/Support/WorkScopeFixture.php';

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(PgTestCase::class)->in('Pg');

require_once __DIR__.'/Authorization/PolicyFixtures.php';
require_once __DIR__.'/Authorization/PolicyTestCase.php';

uses(PolicyTestCase::class)->in('Authorization');
