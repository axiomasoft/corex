<?php

declare(strict_types=1);

use CoreX\Operations\Versioning\Data\ApiVersion;
use CoreX\Operations\Versioning\Data\ApiVersionStatus;

// In PCRE, `$` also matches before a final "\n". A validator written as
// '/^...$/' therefore accepts "v1\n" or an account id with a trailing newline,
// which then reaches storage paths, SQL identifiers or signatures. Anchored
// validators in shipped sources must use the D (dollar end only) modifier.

/** @return list<string> */
function regexLiteralsEndingInDollarWithoutD(): array
{
    $root = dirname(__DIR__, 2);
    $offenders = [];

    foreach ([...glob($root.'/packages/*/src') ?: [], ...glob($root.'/products/*/src') ?: []] as $source) {
        foreach (new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)), '/\.php\z/D') as $file) {
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                if (preg_match('{\A([\'"])([/~#])\^.*\$\2([a-zA-Z]*)\1\z}sD', $token[1], $match) === 1
                    && ! str_contains($match[3], 'D') && ! str_contains($match[3], 'm')) {
                    $offenders[] = substr($file->getPathname(), strlen($root) + 1).':'.$token[2].' '.$token[1];
                }
            }
        }
    }

    return $offenders;
}

it('anchors every shipped validator pattern at the true end of the subject', function (): void {
    expect(regexLiteralsEndingInDollarWithoutD())->toBe([]);
});

it('rejects a version line with a trailing newline', function (): void {
    expect(fn () => new ApiVersion("v1\n", ApiVersionStatus::cases()[0], null))
        ->toThrow(InvalidArgumentException::class);
});
