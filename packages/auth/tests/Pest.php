<?php

declare(strict_types=1);

use CoreX\Auth\Tests\PgTestCase;
use CoreX\Auth\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(PgTestCase::class)->in('Pg');
