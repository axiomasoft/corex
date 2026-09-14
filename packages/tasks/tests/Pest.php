<?php

declare(strict_types=1);

use CoreX\Tasks\Tests\PgTestCase;
use CoreX\Tasks\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(PgTestCase::class)->in('Pg');
