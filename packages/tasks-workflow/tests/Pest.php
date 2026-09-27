<?php

declare(strict_types=1);

use CoreX\TasksWorkflow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses()->group('pg')->in('Pg');
