<?php

declare(strict_types=1);

use CoreX\Crm\Testing\CrmTestCase;
use CoreX\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(CrmTestCase::class, RefreshDatabase::class)->in('Crm');
