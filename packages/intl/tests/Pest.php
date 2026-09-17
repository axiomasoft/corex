<?php

declare(strict_types=1);

use CoreX\Intl\Tests\PgTestCase;
use CoreX\Intl\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Address', 'Locale', 'Money', 'Reference');
uses(PgTestCase::class)->in('Pg');
