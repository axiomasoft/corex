<?php

declare(strict_types=1);

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Config\Internal\Commands\ValidateConfigCommand;
use CoreX\Config\SecretRef;
use CoreX\Testing\ConfigReportTestSection;
use Symfony\Component\Console\Tester\CommandTester;

const CORE_TEST_SECRET_DEFAULT = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

function validateConfigTester(): CommandTester
{
    app()->tag([ConfigReportTestSection::class], ConfigReportSection::class);
    $command = new ValidateConfigCommand;
    $command->setLaravel(app());

    return new CommandTester($command);
}

/**
 * @return array{stdout: string, stderr: string, exception: string, exit: int}
 */
function validateConfigRun(): array
{
    $tester = validateConfigTester();
    $exception = '';

    try {
        $exit = $tester->execute([], ['capture_stderr_separately' => true]);
    } catch (Throwable $thrown) {
        $exit = 1;
        $exception = $thrown->getMessage();
    }

    return [
        'stdout' => $tester->getDisplay(),
        'stderr' => $tester->getErrorOutput(),
        'exception' => $exception,
        'exit' => $exit,
    ];
}

it('exits zero on the default test configuration', function (): void {
    $result = validateConfigRun();

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toContain('driver pubsub.driver=null')
        ->and($result['stdout'])->toContain('adapter pubsub.redis=not-selected')
        ->and($result['stdout'])->toContain('secret corex.test_secret=absent')
        ->and($result['stderr'])->not->toContain('WARNING')
        ->and($result['exception'])->toBe('');
});

it('prints two option violations in one pass and redacts a planted secret', function (): void {
    $secret = 'super-secret-value-test';
    $corex = config('corex');
    $corex['settings']['cache_store'] = '';
    $corex['settings']['cache_ttl'] = -1;
    $corex['pubsub']['driver'] = $secret;

    config([
        'corex' => $corex,
        'corex.test_secret' => new SecretRef($secret),
        'corex.test_cursor_secret' => $secret,
    ]);

    $result = validateConfigRun();
    $combined = $result['stdout'].$result['stderr'].$result['exception'];

    expect($result['exit'])->toBe(1)
        ->and($result['stderr'])->toContain('settings.cache_store: must be a non-empty string')
        ->and($result['stderr'])->toContain('settings.cache_ttl: must be null or a non-negative integer')
        ->and($result['stdout'])->toContain('secret corex.test_secret=present')
        ->and($result['stdout'])->toContain('secret corex.test_cursor_secret=present')
        ->and($result['stdout'])->toContain('driver pubsub.driver=present')
        ->and($combined)->not->toContain($secret);
});

it('warns on the default intl cursor secret without printing it', function (): void {
    config([
        'corex.test_default_secret' => CORE_TEST_SECRET_DEFAULT,
    ]);

    $result = validateConfigRun();
    $combined = $result['stdout'].$result['stderr'].$result['exception'];

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toContain('WARNING [secret-default] corex.test_default_secret')
        ->and($result['stdout'])->toContain('secret corex.test_default_secret=present')
        ->and($combined)->not->toContain(CORE_TEST_SECRET_DEFAULT);
});

it('reports a selected redis adapter without failing when the driver class exists', function (): void {
    $corex = config('corex');
    $corex['pubsub']['driver'] = 'redis';
    config(['corex' => $corex]);

    $result = validateConfigRun();

    expect($result['exit'])->toBe(0)
        ->and($result['stdout'])->toContain('driver pubsub.driver=redis')
        ->and($result['stdout'])->toContain('adapter pubsub.redis=available');
});

it('does not carry errors or secret warnings into a later run of the same command', function (): void {
    $tester = validateConfigTester();
    $validCorex = config('corex');
    $invalidCorex = $validCorex;
    $invalidCorex['settings']['cache_ttl'] = -1;

    config([
        'corex' => $invalidCorex,
        'corex.test_default_secret' => CORE_TEST_SECRET_DEFAULT,
    ]);

    expect($tester->execute([], ['capture_stderr_separately' => true]))->toBe(1)
        ->and($tester->getDisplay())->toContain('WARNING [secret-default]');

    config([
        'corex' => $validCorex,
        'corex.test_default_secret' => null,
    ]);

    expect($tester->execute([], ['capture_stderr_separately' => true]))->toBe(0)
        ->and($tester->getDisplay())->not->toContain('WARNING [secret-default]')
        ->and($tester->getErrorOutput())->toBe('');
});
