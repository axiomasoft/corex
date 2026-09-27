#!/usr/bin/env php
<?php

declare(strict_types=1);

$planRoot = dirname(__DIR__, 3);
$repoRoot = dirname($planRoot, 2);
$srcRoot = dirname(__DIR__).'/src';

require $srcRoot.'/CsvEmitter.php';
require $srcRoot.'/SelfCheck.php';
require $srcRoot.'/InventoryScanner.php';
require $srcRoot.'/GraphBuilder.php';
require $srcRoot.'/TableExtractor.php';
require $srcRoot.'/ConfigExtractor.php';
require $srcRoot.'/DocWriter.php';
require $srcRoot.'/VerdictRules.php';
require $srcRoot.'/Scorecard.php';

$command = $argv[1] ?? 'inventory';
$outDir = $planRoot.'/artifacts/architecture';

switch ($command) {
    case 'inventory':
        $rows = (new InventoryScanner($repoRoot))->scanSrc();
        CsvEmitter::write($outDir.'/files.csv', InventoryScanner::FILE_HEADERS, $rows);
        CsvEmitter::write($outDir.'/symbols.csv', InventoryScanner::SYMBOL_HEADERS, $rows);
        $check = SelfCheck::compare($repoRoot, $rows);
        file_put_contents(
            $outDir.'/self-check.json',
            json_encode($check, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
        fwrite(STDERR, SelfCheck::format($check));
        exit($check['ok'] ? 0 : 1);
    case 'graphs':
        $graphsDir = $outDir.'/graphs';

        if (! is_dir($graphsDir) && ! mkdir($graphsDir, 0755, true) && ! is_dir($graphsDir)) {
            fwrite(STDERR, "cannot create {$graphsDir}\n");
            exit(2);
        }
        $gateConfig = $repoRoot.'/scripts/package-graph-gate.json';
        $builder = new GraphBuilder($repoRoot, $gateConfig);
        $built = $builder->build();
        $encode = static fn (array $payload): string => json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        )."\n";
        file_put_contents($graphsDir.'/composer.json', $encode($built['composer']));
        file_put_contents($graphsDir.'/namespace-import.json', $encode($built['namespaceImport']));
        file_put_contents($graphsDir.'/runtime.json', $encode($built['runtime']));
        file_put_contents($graphsDir.'/discrepancies.md', $built['discrepanciesMarkdown']);
        exit(0);
    case 'tables':
        $extractor = new TableExtractor($repoRoot, $planRoot);
        $rows = $extractor->extract();
        // tables.csv: TABLE_HEADERS + notes
        $tableHeaders = [...TableExtractor::TABLE_HEADERS, 'notes'];
        CsvEmitter::writeRaw($outDir.'/tables.csv', $tableHeaders, $rows);
        // Продуктовый docs/architecture/table-ownership.md
        $docsRoot = $repoRoot.'/docs/architecture';
        DocWriter::writeTables($rows, $docsRoot);
        $count = count($rows);
        fwrite(STDERR, "tables: {$count} unique tables extracted\n");
        fwrite(STDERR, "wrote: {$outDir}/tables.csv\n");
        fwrite(STDERR, "wrote: {$docsRoot}/table-ownership.md\n");
        // Статистика по пакету
        $byPkg = [];
        $sysTables = [];
        foreach ($rows as $row) {
            $pkg = $row['owning_package'];
            $byPkg[$pkg] = ($byPkg[$pkg] ?? 0) + 1;

            if (str_starts_with($row['table'], 'sys_')) {
                $sysTables[] = $row['table'].' ('.$pkg.')';
            }
        }
        ksort($byPkg);
        foreach ($byPkg as $pkg => $cnt) {
            fwrite(STDERR, "  {$pkg}: {$cnt}\n");
        }
        fwrite(STDERR, 'sys_* tables: '.implode(', ', $sysTables)."\n");
        exit(0);
    case 'config':
        $cfgExtractor = new ConfigExtractor($repoRoot, $planRoot);
        $cfgRows = $cfgExtractor->extract();
        $cfgHeaders = [...ConfigExtractor::CONFIG_HEADERS, 'notes'];
        CsvEmitter::writeRaw($outDir.'/config-keys.csv', $cfgHeaders, $cfgRows);
        // Продуктовый docs/architecture/configuration.md
        $docsRoot = $repoRoot.'/docs/architecture';
        DocWriter::writeConfig($cfgRows, $docsRoot);
        $cfgCount = count($cfgRows);
        fwrite(STDERR, "config: {$cfgCount} keys extracted\n");
        fwrite(STDERR, "wrote: {$outDir}/config-keys.csv\n");
        fwrite(STDERR, "wrote: {$docsRoot}/configuration.md\n");
        // Статистика
        $byMode = [];
        $undeclaredCount = 0;
        $undeclaredKeys = [];
        foreach ($cfgRows as $row) {
            $mode = $row['mode'];
            $byMode[$mode] = ($byMode[$mode] ?? 0) + 1;

            if (($row['notes'] ?? '') === 'undeclared-read') {
                $undeclaredCount++;
                $undeclaredKeys[] = $row['key'];
            }
        }
        ksort($byMode);
        foreach ($byMode as $mode => $cnt) {
            fwrite(STDERR, "  mode={$mode}: {$cnt}\n");
        }
        fwrite(STDERR, "undeclared-read ({$undeclaredCount}): ".implode(', ', $undeclaredKeys)."\n");
        exit(0);
    case 'verdicts':
        $rules = new VerdictRules($repoRoot, $planRoot);
        $built = $rules->build();
        $headers = [...InventoryScanner::FILE_HEADERS, ...VerdictRules::VERDICT_EXTRA_HEADERS];
        CsvEmitter::write($outDir.'/verdicts.csv', $headers, $built['rows']);
        $summary = $built['summary'];
        fwrite(STDERR, 'litmus_importer_method: '.$summary['litmus_importer_method']."\n");
        foreach ($summary['location'] as $loc => $cnt) {
            fwrite(STDERR, "  location={$loc}: {$cnt}\n");
        }
        foreach ($summary['surface'] as $surf => $cnt) {
            fwrite(STDERR, "  surface={$surf}: {$cnt}\n");
        }
        $moveList = $summary['core_move'];
        fwrite(STDERR, 'corex/core MOVE candidates: '.count($moveList)."\n");
        foreach ($moveList as $fqcn) {
            fwrite(STDERR, "  {$fqcn}\n");
        }
        fwrite(STDERR, "wrote: {$outDir}/verdicts.csv\n");
        exit(0);
    case 'scorecard':
        $scorecard = new Scorecard($repoRoot, $planRoot);
        $built = $scorecard->build();
        CsvEmitter::writeRaw($outDir.'/scorecard.csv', Scorecard::CSV_HEADERS, $built['rows']);
        $docsRoot = $repoRoot.'/docs/architecture';
        $md = Scorecard::renderMarkdown(
            $built['rows'],
            $built['stats'],
            $built['packages_four_zeros'],
        );
        file_put_contents($docsRoot.'/scorecard.md', $md);
        fwrite(STDERR, 'scorecard rows: '.count($built['rows'])."\n");
        fwrite(STDERR, "wrote: {$outDir}/scorecard.csv\n");
        fwrite(STDERR, "wrote: {$docsRoot}/scorecard.md\n");
        exit(0);
    default:
        fwrite(STDERR, "unknown command: {$command}\n");
        exit(2);
}
