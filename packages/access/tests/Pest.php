<?php

declare(strict_types=1);

use CoreX\Access\Tests\PgTestCase;
use CoreX\Access\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(PgTestCase::class)->in('Pg');
