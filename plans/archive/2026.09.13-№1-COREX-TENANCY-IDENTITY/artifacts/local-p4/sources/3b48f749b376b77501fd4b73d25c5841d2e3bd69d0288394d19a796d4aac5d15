<?php

declare(strict_types=1);

// Фикстура-негатив: тот же код, но путь файла объявлен «внутри corex/tenancy»
// (тест конструирует правило с фрагментом `/tests/PHPStan/data/tenancy/`).
// Ни одной ошибки быть не должно — иначе правило ломает легальный код пакета.

namespace CoreX\Tenancy\Fixture;

use Stancl\Tenancy\Tenancy;

/** Омоним — та же роль, что в фикстуре-позитиве: держит ссылку ниже FQ-формой. */
final class Tenant {}

final class StanclImportInsideFixture
{
    public function imported(): string
    {
        return Tenancy::class;
    }

    public function fullyQualified(): string
    {
        return \Stancl\Tenancy\Database\Models\Tenant::class;
    }
}
