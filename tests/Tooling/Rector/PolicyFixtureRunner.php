<?php

declare(strict_types=1);

namespace CoreX\Tests\Tooling\Rector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/** Uses the same native fixture comparison as the official extension tests. */
final class PolicyFixtureRunner extends AbstractRectorTestCase
{
    private string $configFile;

    public function runFixture(string $fixture, string $configFile): void
    {
        $this->configFile = $configFile;
        parent::setUp();

        try {
            $this->doTestFile($fixture);
        } finally {
            parent::tearDown();
        }
    }

    public function provideConfigFilePath(): string
    {
        return $this->configFile;
    }

    public function fixture(): void {}
}
