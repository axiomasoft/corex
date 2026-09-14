<?php

declare(strict_types=1);

use Flex\Crm\Tests\Support\TestContext;
use Flex\Crm\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->beforeEach(function (): void {
    $this->crmContext = TestContext::seed('testbench');
})->in('Feature');
uses(TestCase::class)->in('Pg');
