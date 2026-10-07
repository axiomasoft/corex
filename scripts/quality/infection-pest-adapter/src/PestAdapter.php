<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Composer\InstalledVersions;
use DOMDocument;
use Infection\AbstractTestFramework\TestFrameworkAdapter;
use Infection\AbstractTestFramework\TestFrameworkAdapterFactory;
use Infection\StreamWrapper\IncludeInterceptor;
use RuntimeException;

/** Runs the whole bounded fragment: Pest's generated test names need no PHPUnit filter translation. */
final readonly class PestAdapter implements TestFrameworkAdapter, TestFrameworkAdapterFactory
{
    /** @param non-empty-string $config */
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
        if ($testFrameworkConfigPath === '') {
            throw new RuntimeException('The Pest configuration path cannot be empty.');
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
        // Keep the raw JUnit for verification; the whole-suite adapter needs no name mapping.
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
        file_put_contents($autoload, "<?php\nrequire_once $interceptor;\n"
            ."\\Infection\\StreamWrapper\\IncludeInterceptor::intercept($original, $mutant);\n"
            ."\\Infection\\StreamWrapper\\IncludeInterceptor::enable();\nrequire_once $bootstrapLiteral;\n");
        $xml->documentElement->setAttribute('bootstrap', $autoload);
        $config = $this->tmp.'/pest.'.$mutationHash.'.xml';
        $xml->save($config);

        return [PHP_BINARY, $this->executable, '--configuration='.$config, '--compact', '--colors=never'];
    }

    public function getInitialTestsFailRecommendations(string $commandLine): string
    {
        return 'Run the recorded Pest command and inspect its exit, coverage XML and JUnit.';
    }
}
