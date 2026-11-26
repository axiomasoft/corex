<?php

declare(strict_types=1);

use CoreX\Testing\CoreXTestCase;

require_once dirname(__DIR__, 3).'/tests/Support/autoload.php';

uses(CoreXTestCase::class)->in('Storage', 'Views');
