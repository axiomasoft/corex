<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\PHPStan;

use CoreX\Tenancy\PHPStan\Rules\StanclImportBoundaryRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * B-11 §7.3 п.1 / AC-29 — `Stancl\*` только в axiomasoft/corex-tenancy.
 *
 * Обе фикстуры лежат в неймспейсе `CoreX\Tenancy\*` и несут одинаковый код:
 * различает их ТОЛЬКО путь. Позитив (`data/outside/`) обязан покраснеть,
 * негатив (`data/tenancy/`, объявлен разрешённым фрагментом пути) — молчать.
 *
 * @extends RuleTestCase<StanclImportBoundaryRule>
 */
final class StanclImportBoundaryRuleTest extends RuleTestCase
{
    /**
     * Прогрев файлового кеша PHPStan ДО первого теста. Холодный кеш даёт
     * промах в `FileCacheStorage::load()` (`@include` несуществующего файла) —
     * подавление `@` PHPUnit здесь не спасает, и первый по случайному порядку
     * тест падал бы `failOnWarning`. Прогрев вне теста: обработчик ошибок
     * PHPUnit ещё не активен, а `failOnWarning` остаётся включённым.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::getContainer();
    }

    /**
     * Разрешённый фрагмент подменён на каталог фикстуры-негатива: продовое
     * значение (`/packages/tenancy/src/`) не покрывает tests/, и негатив без
     * подмены был бы неотличим от позитива.
     */
    protected function getRule(): Rule
    {
        return new StanclImportBoundaryRule(['/tests/PHPStan/data/tenancy/']);
    }

    public function test_stancl_import_outside_tenancy_is_reported(): void
    {
        $this->analyse([__DIR__.'/data/outside/stancl-import.php'], [
            [
                'Importing "Stancl\Tenancy\Tenancy" outside axiomasoft/corex-tenancy is forbidden (B-11 §7.3 п.1, AC-29) — go through the CoreX\Tenancy\Contracts\* seam so the boxed profile stays free of stancl/tenancy.',
                11,
            ],
            [
                'Importing "Stancl\Tenancy\Tenancy" outside axiomasoft/corex-tenancy is forbidden (B-11 §7.3 п.1, AC-29) — go through the CoreX\Tenancy\Contracts\* seam so the boxed profile stays free of stancl/tenancy.',
                25,
            ],
            [
                'Importing "Stancl\Tenancy\Database\Models\Tenant" outside axiomasoft/corex-tenancy is forbidden (B-11 §7.3 п.1, AC-29) — go through the CoreX\Tenancy\Contracts\* seam so the boxed profile stays free of stancl/tenancy.',
                30,
            ],
        ]);
    }

    public function test_stancl_import_inside_tenancy_is_silent(): void
    {
        $this->analyse([__DIR__.'/data/tenancy/stancl-import.php'], []);
    }
}
