<?php

declare(strict_types=1);

use Equipment\Registry\Smoke;
use Illuminate\Foundation\Application;

require dirname(__DIR__).'/vendor/autoload.php';

$mode = $argv[1] ?? '';
$database = $argv[2] ?? '';

if (! in_array($mode, ['schedule', 'worker', 'read'], true) || $database === '') {
    fwrite(STDERR, "usage: smoke.php schedule|worker|read database\n");
    exit(1);
}

$application = new Application(dirname(__DIR__));

if ($application->basePath() === '') {
    fwrite(STDERR, "laravel application did not boot\n");
    exit(1);
}

if ($mode === 'schedule') {
    Smoke::schedule($database);
    echo "scheduled\n";
    exit(0);
}

if ($mode === 'worker') {
    Smoke::work($database);
    echo "worked\n";
    exit(0);
}

echo json_encode(Smoke::read($database), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), "\n";
