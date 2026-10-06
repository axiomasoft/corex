<?php

declare(strict_types=1);

use CoreX\Testing\CoreXTestCase;

final class PublishFailureFixture extends CoreXTestCase
{
    public function test_publish_failure(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'corex-config', '--force' => true])->assertSuccessful();
        $this->assertFileExists(config_path('corex.php'));
        $this->assertSame([], glob(database_path('migrations/*.php')));
        $this->fail('Intentional publish failure; isolated path: '.database_path());
    }

    public function test_next_sqlite_application_is_clean(): void
    {
        $this->assertSame([], glob(database_path('migrations/*.php')));
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }
}
