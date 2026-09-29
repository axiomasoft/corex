<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

require_once __DIR__.'/../vendor/larastan/larastan/bootstrap.php';

(new ConsoleRegistrar)->boot(app());
