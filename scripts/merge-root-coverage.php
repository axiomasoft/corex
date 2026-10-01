<?php

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Serialization\Unserializer;

require __DIR__.'/../vendor/autoload.php';

$minimum = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT);
$floor = (int) file(__DIR__.'/coverage-min-floor.txt', FILE_IGNORE_NEW_LINES)[0];

if ($minimum === false || $minimum < $floor || count($argv) !== 5) {
    fwrite(STDERR, "coverage: require all three reports and the configured floor\n");
    exit(2);
}

$merged = null;
foreach (array_slice($argv, 2) as $path) {
    // Reports are produced by the three successful PHPUnit runs in this checkout.
    $coverage = require $path;

    if (is_array($coverage)) {
        $serialized = (new Unserializer)->unserialize($path);
        $data = $serialized['codeCoverage'];

        if ($serialized['basePath'] !== '') {
            foreach ($data->coveredFiles() as $relativePath) {
                $data->renameFile($relativePath, $serialized['basePath'].DIRECTORY_SEPARATOR.$relativePath);
            }
        }
        $filter = new Filter;
        $coverage = new CodeCoverage((new Selector)->forLineCoverage($filter), $filter);
        $coverage->setData($data);
        $coverage->setTests($serialized['testResults']);
    }

    if (! $coverage instanceof CodeCoverage) {
        throw new RuntimeException('Invalid native coverage report: '.$path);
    }

    if ($merged === null) {
        $merged = $coverage;
    } else {
        $merged->merge($coverage);
    }
}
$report = $merged->getReport();

if ($report->numberOfExecutableLines() === 0) {
    throw new RuntimeException('Coverage reports contain no executable statements.');
}
$percentage = $report->percentageOfExecutedLines()->asFloat();
printf("Merged root line coverage: %.1f%% (%d/%d), minimum %d%%\n", $percentage,
    $report->numberOfExecutedLines(), $report->numberOfExecutableLines(), $minimum);
exit($percentage < $minimum ? 1 : 0);
