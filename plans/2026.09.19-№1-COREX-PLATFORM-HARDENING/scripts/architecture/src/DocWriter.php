<?php

declare(strict_types=1);

// Генерирует продуктовые документы docs/architecture/ из CSV-артефактов.
final class DocWriter
{
    // Шапка table-ownership.md §5.4
    private const TABLE_HEADER = <<<'MD'
# Владение таблицами

Инварианты — статьи `DATA-001` … `DATA-006`. Процедура — `data-ownership-policy.md`.
Эта страница — **каталог текущего состояния**, сгенерированный инвентарём плана
`2026.09.19-№1-COREX-PLATFORM-HARDENING` (P2.3). Она не вводит новых правил.

Поле со значением `unobserved` означает: дерево не дало факта. Это долг
документирования, а не разрешение считать поле пустым.

MD;

    // Шапка configuration.md §6.3
    private const CONFIG_HEADER = <<<'MD'
# Реестр конфигурации

Инварианты — статьи `CFG-001` … `CFG-006`. Процедура — `config-policy.md`.
Эта страница — каталог текущего состояния (P2.4). Типизированные объекты опций
появятся в P7; колонка `typed_options_field` сегодня пуста во всех строках, и это
измеренный факт, а не забытое поле.

MD;

    /**
     * Генерировать docs/architecture/table-ownership.md.
     *
     * @param  list<array<string, string>>  $rows  строки tables.csv
     */
    public static function writeTables(array $rows, string $docsRoot): void
    {
        $out = self::TABLE_HEADER;
        $out .= self::buildSysPrefixSection($rows);
        $out .= "\n";
        $out .= self::buildTableMd($rows);
        $path = $docsRoot.'/table-ownership.md';
        file_put_contents($path, $out);
    }

    // Секция §5.3: разделённый sys_ пересчитывается из данных
    private static function buildSysPrefixSection(array $rows): string
    {
        // Сгруппировать sys_* таблицы по владельцу
        $sysGroups = [];
        foreach ($rows as $row) {
            if (str_starts_with($row['table'], 'sys_')) {
                $pkg = $row['owning_package'];
                $sysGroups[$pkg][] = $row['table'];
            }
        }
        ksort($sysGroups);

        $out = "## Разделённый префикс `sys_`\n\n";
        $out .= "Префикс `sys_` не является владельцем. Сегодня его делят:\n\n";
        $out .= "| Таблицы | Владелец |\n";
        $out .= "|:--|:--|\n";
        foreach ($sysGroups as $pkg => $tables) {
            sort($tables);
            $tableList = implode(', ', array_map(static fn (string $t) => "`{$t}`", $tables));
            $out .= "| {$tableList} | `{$pkg}` |\n";
        }
        $out .= "\n";
        $out .= 'Строки ниже — источник правды по таблице. Расхождение префикса со строкой —';
        $out .= " дефект сканера либо новой миграции, а не повод «починить» префикс.\n";

        return $out;
    }

    // Генерировать MD-таблицу из rows
    private static function buildTableMd(array $rows): string
    {
        $headers = [
            'table', 'owning_package', 'tenant_scope', 'writers', 'readers',
            'pii_class', 'cross_package_access', 'indexes', 'restore_policy',
            'retention_policy', 'upgrade_strategy', 'object_class', 'notes',
        ];
        $labels = [
            'table' => 'table', 'owning_package' => 'owning_package',
            'tenant_scope' => 'tenant_scope', 'writers' => 'writers',
            'readers' => 'readers', 'pii_class' => 'pii_class',
            'cross_package_access' => 'cross_package_access', 'indexes' => 'indexes',
            'restore_policy' => 'restore_policy', 'retention_policy' => 'retention_policy',
            'upgrade_strategy' => 'upgrade_strategy', 'object_class' => 'object_class',
            'notes' => 'notes',
        ];

        $out = "## Каталог таблиц\n\n";
        $out .= '| '.implode(' | ', $headers)." |\n";
        $out .= '|'.implode('|', array_fill(0, count($headers), ':--'))."|\n";
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                // Экранировать | в ячейках
                $val = str_replace('|', '\\|', $val);
                $cells[] = $val;
            }
            $out .= '| '.implode(' | ', $cells)." |\n";
        }

        return $out;
    }

    /**
     * Генерировать docs/architecture/configuration.md.
     *
     * @param  list<array<string, string>>  $rows  строки config-keys.csv
     */
    public static function writeConfig(array $rows, string $docsRoot): void
    {
        $out = self::CONFIG_HEADER;
        $out .= self::buildConfigMd($rows);
        $path = $docsRoot.'/configuration.md';
        file_put_contents($path, $out);
    }

    private static function buildConfigMd(array $rows): string
    {
        $headers = [
            'key', 'owner', 'type', 'default', 'secret', 'mode',
            'reloadable', 'validation', 'typed_options_field', 'deprecated_aliases', 'notes',
        ];

        $out = "## Каталог ключей\n\n";
        $out .= '| '.implode(' | ', $headers)." |\n";
        $out .= '|'.implode('|', array_fill(0, count($headers), ':--'))."|\n";
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $val = $row[$h] ?? '';
                $val = str_replace('|', '\\|', $val);
                $cells[] = $val;
            }
            $out .= '| '.implode(' | ', $cells)." |\n";
        }

        return $out;
    }
}
