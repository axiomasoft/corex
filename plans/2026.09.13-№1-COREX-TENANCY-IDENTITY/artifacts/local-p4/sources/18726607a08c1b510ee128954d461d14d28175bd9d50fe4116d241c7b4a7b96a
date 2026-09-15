<?php

declare(strict_types=1);

// Фикстура-нарушение: файл ВНЕ corex/tenancy. Неймспейс намеренно `CoreX\Tenancy\*` —
// именно так выглядят контракты, физически живущие в corex/core (D13), и именно их
// namespace-проверка пропустила бы. Дискриминатор правила — путь файла.

namespace CoreX\Tenancy\Contracts\Fixture;

use Stancl\Tenancy\Tenancy;

/**
 * Локальный омоним: короткое имя `Tenant` занято в этом неймспейсе, поэтому
 * ссылка на вендорный `Tenant` ниже остаётся полностью-квалифицированной.
 * Без омонима Pint (`fully_qualified_strict_types`) свернул бы её в импорт, и
 * фикстура потеряла бы покрытие второй формы ссылки, которую ловит правило.
 */
final class Tenant {}

final class StanclImportOutsideFixture
{
    public function imported(): string
    {
        return Tenancy::class;
    }

    public function fullyQualified(): string
    {
        return \Stancl\Tenancy\Database\Models\Tenant::class;
    }

    /**
     * Докблок-упоминание `Stancl\Tenancy` легально: правило смотрит AST, а не текст —
     * ровно поэтому `packages/core/src/Tenancy/Contracts/TenantDatabaseLifecycle.php`
     * остаётся зелёным.
     */
    public function mentionInCommentIsLegal(): string
    {
        // Stancl\Tenancy — упомянут только в комментарии.
        return 'Stancl\Tenancy';
    }
}
