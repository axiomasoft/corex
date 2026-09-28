<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests\Unit;

use AzGuard\AzGuardServiceProvider;
use CoreX\Auth\CoreAuthServiceProvider;
use CoreX\Auth\Support\AccessConfigSection;
use CoreX\Contracts\ConfigReportSection;
use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, AzGuardServiceProvider::class, CoreAuthServiceProvider::class];
    }

    public function test_section_registers_in_report_order_and_redacts_its_secrets(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof AccessConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(20, $owned[0]->order());
        $code = Artisan::call('corex:config:validate');
        self::assertSame(0, $code);
        self::assertStringContainsString('driver pubsub.driver=', Artisan::output());
    }
}
