<?php

declare(strict_types=1);

namespace CoreX\Tests\Tooling\Rector;

use CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch\ServerVariableToRequestFacadeRector;
use CoreX\Tooling\Rector\Laravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;

require_once __DIR__.'/PolicyFixtureRunner.php';

final class PolicyRegressionTest extends TestCase
{
    public function test_dispatch_preserves_registered_production_directory_and_pending_dispatch_call(): void
    {
        $before = "<?php\n\nclass PolicyJob\n{\n    use \\Illuminate\\Foundation\\Bus\\Dispatchable;\n}\n\nPolicyJob::dispatch('payload');\n";
        $after = "<?php\n\nclass PolicyJob\n{\n    use \\Illuminate\\Foundation\\Bus\\Dispatchable;\n}\n\ndispatch(new \\PolicyJob('payload'));\n";
        $this->compare(DispatchToHelperFunctionsRector::class, $before, $before, 'directory');
        $this->compare(DispatchToHelperFunctionsRector::class, $before, $after, 'none');
        $this->compare(DispatchToHelperFunctionsRector::class, $before, $after, 'adjacent-directory');
    }

    public function test_cli_server_reads_remain_request_free_only_in_registered_file(): void
    {
        $before = "<?php\n\n\$arguments = \$_SERVER['argv'] ?? [];\n";
        $after = "<?php\n\n\$arguments = \\Illuminate\\Support\\Facades\\Request::server('argv') ?? [];\n";
        $this->compare(ServerVariableToRequestFacadeRector::class, $before, $before, 'file');
        $this->compare(ServerVariableToRequestFacadeRector::class, $before, $after, 'none');
        $this->compare(ServerVariableToRequestFacadeRector::class, $before, $after, 'adjacent-file');
    }

    public function test_policy_configuration_rejects_unbounded_or_malformed_paths(): void
    {
        foreach ([DispatchToHelperFunctionsRector::class, ServerVariableToRequestFacadeRector::class] as $class) {
            foreach ([['other' => []], ['preserve_paths' => 'src'], ['preserve_paths' => ['relative']], ['preserve_paths' => ['/']], ['preserve_paths' => ['/corex-nonexistent-policy-path']], ['preserve_paths' => [1]], ['preserve_paths' => ['x' => '/tmp']]] as $configuration) {
                $rule = new ReflectionClass($class)->newInstanceWithoutConstructor();

                try {
                    $rule->configure($configuration);
                    self::fail('Malformed preservation policy was accepted');
                } catch (InvalidArgumentException $exception) {
                    self::assertNotSame('', $exception->getMessage());
                }
            }
        }
    }

    public function test_optional_binding_identifier_stays_data_while_other_class_names_modernize(): void
    {
        $before = "<?php\n\nfunction inspectBinding(\$application): array\n{\n    return [\$application->bound('Laravel\\Octane\\Octane'), 'Illuminate\\Support\\Collection'];\n}\n";
        $after = "<?php\n\nfunction inspectBinding(\$application): array\n{\n    return [\$application->bound('Laravel\\Octane\\Octane'), \\Illuminate\\Support\\Collection::class];\n}\n";

        $this->compare(StringClassNameToClassConstantRector::class, $before, $after, 'class-identifiers');
    }

    /** @param class-string<ConfigurableRectorInterface> $class */
    private function compare(string $class, string $before, string $after, string $mode): void
    {
        $directory = sys_get_temp_dir().'/corex-rector-policy-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->mkdir([$directory.'/production', $directory.'/production-adjacent']);

        try {
            $sourceDirectory = $mode === 'adjacent-directory' ? $directory.'/production-adjacent' : $directory.'/production';
            $fixture = $sourceDirectory.'/fixture.php.inc';
            $inputPath = $sourceDirectory.'/fixture.php';
            $files->dumpFile($inputPath, $before);
            $files->dumpFile($directory.'/production/other.php', $before);
            $files->dumpFile($fixture, $before === $after ? $before : $before."-----\n".$after);
            $paths = match ($mode) {
                'directory', 'adjacent-directory' => [$directory.'/production'],
                'file' => [$inputPath],
                'adjacent-file' => [$directory.'/production/other.php'],
                default => [],
            };
            $config = '<?php'.PHP_EOL.'return static function (\\Rector\\Config\\RectorConfig $config): void {'.PHP_EOL;
            $configuration = $mode === 'class-identifiers' ? ['Laravel\\Octane\\Octane'] : ['preserve_paths' => $paths];
            $config .= '$config->ruleWithConfiguration(\\'.$class.'::class, '.var_export($configuration, true).');'.PHP_EOL.'};'.PHP_EOL;
            $files->dumpFile($directory.'/config.php', $config);
            $runner = new PolicyFixtureRunner('fixture');
            $runner->runFixture($fixture, $directory.'/config.php');
            self::assertFileExists($fixture);
        } finally {
            $files->remove($directory);
        }
    }
}
