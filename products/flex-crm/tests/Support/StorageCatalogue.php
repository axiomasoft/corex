<?php

declare(strict_types=1);

namespace Flex\Crm\Tests\Support;

use Illuminate\Database\Connection;

final class StorageCatalogue
{
    /** @return array<string, array<string, mixed>> */
    public static function read(Connection $db, string $schema): array
    {
        $result = [];
        foreach ($db->select("SELECT tablename FROM pg_tables WHERE schemaname = ? AND tablename LIKE 'crm_%' ORDER BY tablename", [$schema]) as $row) {
            $table = $row->tablename;
            $columns = $db->select('SELECT a.attname AS name, format_type(a.atttypid,a.atttypmod) AS type, a.attnotnull AS not_null, pg_get_expr(d.adbin,d.adrelid) AS default_value FROM pg_attribute a JOIN pg_class c ON c.oid=a.attrelid JOIN pg_namespace n ON n.oid=c.relnamespace LEFT JOIN pg_attrdef d ON d.adrelid=c.oid AND d.adnum=a.attnum WHERE n.nspname=? AND c.relname=? AND a.attnum>0 AND NOT a.attisdropped ORDER BY a.attname', [$schema, $table]);
            $constraints = $db->select('SELECT contype AS type, pg_get_constraintdef(k.oid) AS definition FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname=? AND c.relname=? ORDER BY contype, pg_get_constraintdef(k.oid)', [$schema, $table]);
            $indexes = $db->select('SELECT i.indisunique AS is_unique, i.indnullsnotdistinct AS nulls_not_distinct, am.amname AS method, pg_get_indexdef(i.indexrelid, 0, true) AS definition FROM pg_index i JOIN pg_class c ON c.oid=i.indrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_class ic ON ic.oid=i.indexrelid JOIN pg_am am ON am.oid=ic.relam WHERE n.nspname=? AND c.relname=?', [$schema, $table]);
            $normalize = static fn (string $value): string => str_replace([$schema.'.', 'public.', 'CURRENT_TIMESTAMP'], ['', '', 'now()'], $value);
            $indexRows = array_map(static fn (object $r): array => ['is_unique' => $r->is_unique, 'nulls_not_distinct' => $r->nulls_not_distinct, 'method' => $r->method, 'definition' => $normalize((string) preg_replace('/^CREATE (UNIQUE )?INDEX \S+ ON \S+ /', '', $r->definition))], $indexes);
            usort($indexRows, static fn (array $a, array $b): int => strcmp(json_encode($a, JSON_THROW_ON_ERROR), json_encode($b, JSON_THROW_ON_ERROR)));
            $result[$table] = ['columns' => array_map(static fn (object $r): array => ['name' => $r->name, 'type' => $r->type, 'not_null' => $r->not_null, 'default' => $r->default_value === null ? null : $normalize($r->default_value)], $columns), 'constraints' => array_map(static fn (object $r): array => ['type' => $r->type, 'definition' => $normalize($r->definition)], $constraints), 'indexes' => $indexRows];
        }

        return $result;
    }
}
