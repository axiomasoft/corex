<?php

declare(strict_types=1);

use CoreX\Panels\Tests\SavedViewsTestCase;
use CoreX\Panels\Tests\TestCase;

require_once dirname(__DIR__, 3).'/tests/Support/autoload.php';

uses(TestCase::class)->in('Unit');

uses(SavedViewsTestCase::class)->in('SavedViews');
