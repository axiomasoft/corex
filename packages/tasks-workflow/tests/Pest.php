<?php

declare(strict_types=1);

use CoreX\TasksWorkflow\Tests\PgTestCase;
use CoreX\TasksWorkflow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(PgTestCase::class)->in('Pg');
