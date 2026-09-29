<?php

declare(strict_types=1);

namespace CoreX\Media\Tests;

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\CoreServiceProvider;
use CoreX\Media\MediaServiceProvider;
use CoreX\Media\Support\MediaConfigSection;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, MediaServiceProvider::class];
    }

    public function test_section_registers_in_report_order_and_redacts_its_secrets(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof MediaConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(30, $owned[0]->order());
        $code = Artisan::call('corex:config:validate');
        self::assertSame(0, $code);
        self::assertStringContainsString('driver pubsub.driver=', Artisan::output());
    }
}
