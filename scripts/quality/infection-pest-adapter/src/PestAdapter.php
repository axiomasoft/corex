<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Composer\InstalledVersions;
use DOMDocument;
use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\AbstractTestFramework\TestFrameworkAdapterFactory;
use Infection\StreamWrapper\IncludeInterceptor;
use RuntimeException;

/** Run every covering test class, retaining all methods and datasets. */
final readonly class PestAdapter implements TestFrameworkAdapter, TestFrameworkAdapterFactory
{
    /** @param non-empty-string $config
     * @param  non-empty-string  $junit
     */
    public function __construct(
        private string $executable,
        private string $tmp,
        private string $config,
        private string $junit,
    ) {}

    /** @param array<mixed> $sourceDirectories */
    public static function create(
        string $testFrameworkExecutable,
        string $tmpDir,
        string $testFrameworkConfigPath,
        ?string $testFrameworkConfigDir,
        string $jUnitFilePath,
        string $projectDir,
        array $sourceDirectories,
        bool $skipCoverage,
    ): TestFrameworkAdapter {
        if ($testFrameworkConfigPath === '' || $jUnitFilePath === '') {
            throw new RuntimeException('The Pest configuration and JUnit paths cannot be empty.');
        }

        return new self(
            executable: $testFrameworkExecutable,
            tmp: $tmpDir,
            config: $testFrameworkConfigPath,
            junit: $jUnitFilePath,
        );
    }

    public static function getAdapterName(): string
    {
        return 'pest-spike';
    }

    public static function getExecutableName(): string
    {
        return 'corex-pest';
    }

    public function getName(): string
    {
        return 'Pest';
    }

    public function getVersion(): string
    {
        return ltrim(InstalledVersions::getPrettyVersion('pestphp/pest') ?? '', 'v');
    }

    public function hasJUnitReport(): bool
    {
        // Pest pretty-prints JUnit class names but coverage uses generated P\\ classes.
        // Keep raw JUnit for our explicit mapping; Infection's own name mapping is incompatible.
        return false;
    }

    public function testsPass(string $output): bool
    {
        $output = preg_replace('/\x1b\[[0-9;]*m/', '', $output) ?? '';

        return preg_match('/Tests:\s+[1-9][0-9]* passed\s+\([1-9][0-9]* assertions\)/', $output) === 1
            && preg_match('/\b(?:failed|skipped|risky|warnings)\b/i', $output) === 0;
    }

    public function getInitialTestRunCommandLine(string $extraOptions, array $phpExtraArgs, bool $skipCoverage): array
    {
        if ($extraOptions !== '') {
            throw new RuntimeException('The bounded adapter does not accept unverified extra options.');
        }

        $command = [PHP_BINARY, ...array_filter($phpExtraArgs, static fn (string $arg): bool => $arg !== ''), $this->executable, '--configuration='.$this->config, '--compact', '--colors=never'];

        if (! $skipCoverage) {
            $command[] = '--coverage-xml='.$this->tmp.'/coverage-xml';
            $command[] = '--log-junit='.$this->junit;
            $command[] = '--record-test-run-history';
            $command[] = '--cache-directory='.$this->tmp.'/test-history';
        }

        return $command;
    }

    public function getMutantCommandLine(
        array $coverageTests,
        string $mutatedFilePath,
        string $mutationHash,
        string $mutationOriginalFilePath,
        string $extraOptions,
    ): array {
        if ($extraOptions !== '') {
            throw new RuntimeException('The bounded adapter does not accept unverified extra options.');
        }

        $junit = new DOMDocument;

        if (! $junit->load($this->junit)) {
            throw new RuntimeException('Missing covering-test JUnit mapping.');
        }
        $names = [];
        foreach ($junit->getElementsByTagName('testcase') as $case) {
            $class = str_replace('\\\\', '\\', $case->getAttribute('class'));
            $name = $case->getAttribute('name');
            $names[$class][$name] = true;
        }
        $methods = [];
        $classes = [];
        foreach ($coverageTests as $test) {
            if (! str_contains($test->getMethod(), '::')) {
                throw new RuntimeException('Unknown covering test identity.');
            }
            [$class, $method] = explode('::', $test->getMethod(), 2);
            // Coverage escapes namespace separators; PHPUnit filters use the actual class.
            $class = str_replace('\\\\', '\\', $class);
            $method = explode('#', $method, 2)[0];
            $pest = str_starts_with($class, 'P\\') && str_starts_with($method, '__pest_evaluable_');

            if ($pest) {
                // Pest's NameFilterIterator matches its printable class, without the generated P prefix.
                $class = substr($class, 2);
            }

            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\\\\[a-zA-Z_][a-zA-Z0-9_]*)*$/D', $class) !== 1) {
                throw new RuntimeException('Unknown covering test class.');
            }
            $classes[preg_quote($class, '/')] = true;

            if (! $pest) {
                // Pest's JUnit formatter uses TestDox for PHPUnit cases; their filter retains PHP method names.
                if (! isset($names[$class]) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $method) !== 1) {
                    throw new RuntimeException('Unknown covering PHPUnit test method.');
                }
                $methods[preg_quote($class.'::'.$method, '/')] = true;

                continue;
            }
            $matched = false;
            foreach (array_keys($names[$class] ?? []) as $name) {
                foreach (array_unique([$name, explode(' with data set ', $name, 2)[0]]) as $candidate) {
                    if (self::evaluable($candidate) === $method) {
                        $methods[preg_quote($class.'::'.$candidate, '/')] = true;
                        $matched = true;
                    }
                }
            }

            if (! $matched) {
                throw new RuntimeException('Unknown covering test method in fresh JUnit: '.$test->getMethod());
            }
        }

        if ($methods === []) {
            throw new RuntimeException('No covering test methods for an executed mutant.');
        }
        // Broadly covered registrars produced 53KB method patterns beyond PCRE's compile limit.
        // Selecting the whole covering class retains every reported method and its datasets.
        $filter = '/^(?:'.implode('|', array_keys($classes)).')::/';

        $xml = new DOMDocument;

        if (! $xml->load($this->config) || $xml->documentElement === null) {
            throw new RuntimeException('Missing or invalid Pest XML configuration.');
        }
        $bootstrap = $xml->documentElement->getAttribute('bootstrap');
        $autoload = $this->tmp.'/bootstrap.'.$mutationHash.'.php';
        $interceptor = var_export(IncludeInterceptor::LOCATION, true);
        $original = var_export($mutationOriginalFilePath, true);
        $mutant = var_export($mutatedFilePath, true);
        $bootstrapLiteral = var_export($bootstrap, true);
        $early = $this->tmp.'/interceptor.'.$mutationHash.'.php';
        file_put_contents($early, "<?php\nrequire_once $interceptor;\n"
            ."\\Infection\\StreamWrapper\\IncludeInterceptor::intercept($original, $mutant);\n"
            ."\\Infection\\StreamWrapper\\IncludeInterceptor::enable();\n");
        $earlyLiteral = var_export($early, true);
        file_put_contents($autoload, "<?php\nrequire_once $earlyLiteral;\nrequire_once $bootstrapLiteral;\n");
        $xml->documentElement->setAttribute('bootstrap', $autoload);
        $config = $this->tmp.'/pest.'.$mutationHash.'.xml';
        $xml->save($config);
        // PHPUnit 13 requires recording to sort by duration. Each trial receives its own
        // copy of the fresh initial history, so earlier mutants cannot affect its order.
        $history = $this->tmp.'/test-history-'.$mutationHash;

        if (! is_dir($history) && ! mkdir($history, 0700)) {
            throw new RuntimeException('Cannot isolate mutant test history.');
        }
        $historyFiles = glob($this->tmp.'/test-history/*');

        if ($historyFiles === false) {
            throw new RuntimeException('Cannot read fresh initial test history.');
        }
        $historyFiles = array_filter($historyFiles, is_file(...));

        if ($historyFiles === []) {
            throw new RuntimeException('Missing fresh initial test history.');
        }
        foreach ($historyFiles as $file) {
            if (! copy($file, $history.'/'.basename($file))) {
                throw new RuntimeException('Cannot copy fresh initial test history.');
            }
        }

        // The pinned CLI parser splits --option=value on every '='; test descriptions may contain '='.
        return [PHP_BINARY, $this->executable, '--configuration='.$config, '--compact', '--colors=never', '--filter', $filter,
            '--cache-directory='.$history, '--order-by=duration-ascending', '--record-test-run-history',
            '--stop-on-failure', '--stop-on-error'];
    }

    public function getInitialTestsFailRecommendations(string $commandLine): string
    {
        return 'Run the recorded Pest command and inspect its exit, coverage XML and JUnit.';
    }

    private static function evaluable(string $description): string
    {
        // This version-bound encoding is verified against real Pest-generated coverage and JUnit.
        $description = str_replace('_', '__', $description);
        $description = '__pest_evaluable_'.str_replace(' ', '_', $description);

        return preg_replace('/[^a-zA-Z0-9_\x80-\xff]/', '_', $description) ?? throw new RuntimeException('Invalid Pest method encoding.');
    }
}
