<?php

declare(strict_types=1);
use CoreX\Tools\Internal\ToolSchemaWorker;

$mode = $argv[1];
file_put_contents($argv[2], (string) getmypid());

if ($mode === 'slow-start') {
    usleep(300_000);
    require __DIR__.'/../../src/Internal/ToolSchemaWorker.php';
    ToolSchemaWorker::runCli();
    exit;
}

if ($mode === 'no-ready') {
    sleep(10);
    exit;
}

if ($mode === 'bad-ready') {
    echo "wrong\n";
    exit;
}

if ($mode === 'eof') {
    exit;
}

if ($mode === 'coalesced') {
    echo "ready\n".'{"ok":true,"validation":{"valid":true,"errors":[]}}';
    fflush(STDOUT);
    stream_get_contents(STDIN);
    exit;
}

if ($mode === 'partial-ready') {
    echo 'rea';
    fflush(STDOUT);
    usleep(20_000);
    echo "dy\n";
} else {
    echo "ready\n";
}
fflush(STDOUT);

if ($mode === 'blocked-input') {
    sleep(10);
    exit;
}
stream_get_contents(STDIN);

if ($mode === 'timeout' || $mode === 'ignore-terminate') {
    if ($mode === 'ignore-terminate') {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, SIG_IGN);
    }
    sleep(10);
    exit;
}

if ($mode === 'memory') {
    str_repeat('x', 80 * 1024 * 1024);
    exit;
}

if ($mode === 'stderr') {
    fwrite(STDERR, str_repeat('x', 256 * 1024));
}
$response = match ($mode) {
    'malformed' => 'not json',
    'truncated' => '{"ok":true,"validation":',
    'wrong-shape' => '{"ok":true,"validation":{"valid":"true","errors":[]}}',
    'wrong-errors' => '{"ok":true,"validation":{"valid":false,"errors":["invalid"]}}',
    'missing-errors' => '{"ok":true,"validation":{"valid":true}}',
    'wrong-handle' => '{"ok":true,"handle":{"digest":false}}',
    'failed' => '{"ok":false,"error":"schema_limit"}',
    'contradictory' => '{"ok":true,"validation":{"valid":true,"errors":[{"code":"type","pointer":"/"}]}}',
    default => '{"ok":true,"validation":{"valid":true,"errors":[]}}',
};
echo $response;

if ($mode === 'trailing') {
    echo '{}';
}
