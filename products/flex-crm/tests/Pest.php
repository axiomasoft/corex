<?php

declare(strict_types=1);

use Flex\Crm\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once dirname(__DIR__, 3).'/tests/Support/autoload.php';

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Pg');
