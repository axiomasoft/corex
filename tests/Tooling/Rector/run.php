<?php

declare(strict_types=1);

use CoreX\Tooling\Rector\Import\SourceSnapshot;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
require $root.'/scripts/quality/rector/Import/SourceSnapshot.php';

$directory = sys_get_temp_dir().'/corex-rector-official-'.bin2hex(random_bytes(8));
$filesystem = new Filesystem;
$filesystem->mkdir($directory);

try {
    $snapshot = SourceSnapshot::load($root.'/scripts/quality/rector/upstream.json');
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $finder = new NodeFinder;
    $stubClasses = [];
    $fixtureCount = 0;
    $testClasses = 0;

    foreach ($snapshot->files as $path => $file) {
        if (! str_starts_with($path, 'tests/') && ! str_starts_with($path, 'stubs/')) {
            continue;
        }

        $code = $file['text'];

        if (str_contains($path, '/config/')) {
            $code = str_replace('RectorLaravel\\Rector\\', 'CoreX\\Tooling\\Rector\\Laravel\\Rector\\', $code);
        }

        $target = $directory.'/'.$path;
        $filesystem->mkdir(dirname($target));
        $filesystem->dumpFile($target, $code);

        if (str_contains($path, '/Fixture/')) {
            $fixtureCount++;
        }

        if (str_ends_with($path, 'Test.php')) {
            $testClasses++;
        }

        if (! str_starts_with($path, 'stubs/') || ! str_ends_with($path, '.php')) {
            continue;
        }

        $nodes = $parser->parse($code);

        if ($nodes === null) {
            throw new RuntimeException('Invalid official stub: '.$path);
        }

        $nodes = new NodeTraverser(new NameResolver)->traverse($nodes);

        foreach ($finder->findInstanceOf($nodes, ClassLike::class) as $class) {
            if ($class->name !== null && $class->namespacedName !== null) {
                $stubClasses[$class->namespacedName->toString()] = $target;
            }
        }
    }

    if ($fixtureCount !== 173 || $testClasses !== 36) {
        throw new RuntimeException('Incomplete official regression suite');
    }

    $filesystem->mirror($root.'/scripts/quality/rector/Laravel/config', $directory.'/config');
    $bootstrap = '<?php'.PHP_EOL.'require '.var_export($root.'/vendor/autoload.php', true).';'.PHP_EOL;
    $bootstrap .= '$loader = new \\Composer\\Autoload\\ClassLoader('.var_export($directory.'/vendor', true).');'.PHP_EOL;
    $bootstrap .= '$loader->addClassMap('.var_export($stubClasses, true).');'.PHP_EOL;
    $bootstrap .= '$loader->addPsr4("RectorLaravel\\\\Tests\\\\", '.var_export($directory.'/tests', true).');'.PHP_EOL;
    $bootstrap .= '$loader->register(true);'.PHP_EOL;
    $filesystem->dumpFile($directory.'/bootstrap.php', $bootstrap);
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
    $xml .= '<phpunit bootstrap="bootstrap.php" colors="false" failOnWarning="true" failOnRisky="true" failOnEmptyTestSuite="true" cacheDirectory="cache"><testsuites><testsuite name="Official Laravel subset"><directory>tests/Rector</directory><file>'.htmlspecialchars(__DIR__.'/PolicyRegressionTest.php', ENT_XML1).'</file></testsuite></testsuites></phpunit>';
    $filesystem->dumpFile($directory.'/phpunit.xml', $xml);

    $process = new Process([PHP_BINARY, $root.'/vendor/bin/phpunit', '--configuration', $directory.'/phpunit.xml', '--no-coverage', '--display-all-issues'], $root);
    $process->setTimeout(300);
    $exit = $process->run();
    echo $process->getOutput();
    fwrite(STDERR, $process->getErrorOutput());
    echo 'Official fixture inventory: '.$fixtureCount.' unique fixtures, '.$testClasses.' rule test classes; stubs confined to child process.'.PHP_EOL;

} finally {
    $filesystem->remove($directory);
}

exit($exit);
