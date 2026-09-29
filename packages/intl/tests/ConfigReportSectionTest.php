<?php

declare(strict_types=1);

namespace CoreX\Intl\Tests;

use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\CoreServiceProvider;
use CoreX\Intl\Internal\IntlConfigSection;
use CoreX\Intl\IntlServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

final class ConfigReportSectionTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, IntlServiceProvider::class];
    }

    public function test_section_registers_in_report_order_and_redacts_its_secrets(): void
    {
        $sections = iterator_to_array($this->app->tagged(ConfigReportSection::class));
        $owned = array_values(array_filter($sections, static fn (ConfigReportSection $section): bool => $section instanceof IntlConfigSection));
        self::assertCount(1, $owned);
        self::assertSame(40, $owned[0]->order());
        config(['corex-intl.reference.cursor_secret' => 'a-private-section-secret']);
        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('secret corex-intl.reference.cursor_secret=present', Artisan::output());
        self::assertStringNotContainsString('a-private-section-secret', Artisan::output());
        config(['corex-intl.reference.cursor_secret' => null]);
        self::assertSame(0, Artisan::call('corex:config:validate'));
        self::assertStringContainsString('secret corex-intl.reference.cursor_secret=absent', Artisan::output());
    }
}
