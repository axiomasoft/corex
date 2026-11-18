<?php

declare(strict_types=1);

namespace CoreX\Access\Tests;

use CoreX\Access\AccessServiceProvider;
use CoreX\Access\Internal\Support\AccessConfigSection;
use CoreX\CoreServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use Packman\Config\Contracts\ConfigReportSectionInterface as ConfigReportSection;
use Packman\Modules\ModulesServiceProvider;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, AccessServiceProvider::class];
    }

    public function test_section_registers_in_report_order_and_redacts_its_secrets(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof AccessConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(20, $owned[0]->order());
        $code = Artisan::call('packman:config:validate');
        self::assertSame(0, $code);
        self::assertStringContainsString('driver pubsub.driver=', Artisan::output());
    }
}
