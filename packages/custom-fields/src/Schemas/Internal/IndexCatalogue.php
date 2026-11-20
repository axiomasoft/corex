<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final class IndexCatalogue
{
    /** @return array<string,mixed> */
    public static function plan(Connection $db, DataScope $scope, FieldDefinition $field, string $slot, string $namespace): array
    {
        $parent = self::parent($db, $namespace);
        $cast = match ($field->type->value) {
            'int' => 'bigint', 'decimal' => 'numeric', 'text', 'select', 'email', 'url', 'phone' => 'text',
            default => throw new SchemaConflict('index-type-unavailable'),
        };

        if ($field->localizationPolicy !== 'none' || $field->codecVersion !== 1) {
            throw new SchemaConflict('index-codec-unavailable');
        }

        if ($parent['owner_name'] !== $db->getConfig('username')) {
            throw new SchemaConflict('physical-parent-role-mismatch');
        }

        if ($cast !== 'text' && SchemaRows::rows($db, SchemaRows::DOCUMENTS, $scope, $field->entity->value)
            ->whereRaw('slots ->> CAST(? AS text) IS NOT NULL AND NOT pg_catalog.pg_input_is_valid(slots ->> CAST(? AS text), ?)', [$slot, $slot, $cast])->exists()) {
            throw new SchemaConflict('index-source-invalid');
        }
        $name = 'sys_field_ix_'.substr(SchemaRows::digest([SchemaRows::logical($scope), $field->entity->value, $slot, $cast]), 0, 40);
        $literals = [];
        foreach ([...SchemaRows::logical($scope), 'entity' => $field->entity->value] as $column => $value) {
            $literals[$column] = $db->getPdo()->quote($value);
        }
        $slotSql = $db->getPdo()->quote($slot);
        $expr = $cast === 'text' ? '(slots ->> '.$slotSql.'::text)' : '((slots ->> '.$slotSql.'::text))::'.$cast;
        $conditions = [];
        foreach ($literals as $column => $literal) {
            $conditions[] = '(('.$column.')::text = '.$literal.'::text)';
        }
        $spec = [...$parent, 'name' => $name, 'expression' => $expr, 'predicate' => '('.implode(' AND ', $conditions).')',
            'cast' => $cast, 'method' => 'btree', 'slot' => $slot];
        $spec['digest'] = SchemaRows::digest($spec);

        return $spec;
    }

    /** @return array<string,string> */
    public static function parent(Connection $db, string $namespace): array
    {
        $row = $db->selectOne(<<<'SQL'
            SELECT current_database() AS database_name, d.oid::text AS database_oid, n.nspname AS namespace, n.oid::text AS namespace_oid,
                   c.relname AS parent_name, c.oid::text AS parent_oid, c.relowner::text AS owner_oid, pg_get_userbyid(c.relowner) AS owner_name
              FROM pg_catalog.pg_namespace n JOIN pg_catalog.pg_class c ON c.relnamespace=n.oid
              JOIN pg_catalog.pg_database d ON d.datname=current_database()
             WHERE n.nspname=? AND c.relname=? AND c.relkind='r' AND NOT c.relispartition
            SQL, [$namespace, SchemaRows::DOCUMENTS], false);

        if (! $row instanceof stdClass) {
            throw new SchemaConflict('physical-parent-missing');
        }

        return array_map(SchemaValue::text(...), SchemaValue::map(get_object_vars($row)));
    }

    /** @param array<string,mixed> $spec */
    public static function verifyParent(Connection $db, array $spec): void
    {
        $parent = self::parent($db, SchemaValue::text($spec['namespace']));
        foreach ($parent as $column => $value) {
            if (($spec[$column] ?? null) !== $value) {
                throw new SchemaConflict('physical-parent-replaced');
            }
        }
        $digest = $spec['digest'];
        unset($spec['digest']);

        if ($digest !== SchemaRows::digest($spec)) {
            throw new SchemaConflict('physical-index-plan-conflict');
        }
    }

    /** @param array<string,mixed> $spec */
    public static function inspect(Connection $db, array $spec): ?stdClass
    {
        $row = $db->selectOne(<<<'SQL'
            SELECT x.oid::text AS oid, n.oid::text AS namespace_oid, x.relowner::text AS owner_oid, x.relkind, m.amname,
                   x.relfilenode::text AS filenode, i.indrelid::text AS parent_oid, i.indisvalid, i.indisready, i.indislive,
                   pg_get_indexdef(x.oid) AS definition, pg_get_expr(i.indexprs,i.indrelid,false) AS expression,
                   pg_get_expr(i.indpred,i.indrelid,false) AS predicate, pg_get_indexdef(x.oid,2,false) AS tie_key,
                   (NOT i.indisunique AND NOT i.indisprimary AND NOT i.indisexclusion AND NOT i.indisreplident AND NOT i.indisclustered
                    AND NOT x.relispartition AND x.reloptions IS NULL AND i.indnatts=2 AND i.indnkeyatts=2
                    AND i.indkey[0]=0 AND i.indkey[1]=a.attnum AND i.indoption::text='0 0'
                    AND i.indcollation[0]=CASE WHEN opc.opcname='text_ops' THEN 'pg_catalog."default"'::regcollation::oid ELSE 0 END
                    AND i.indcollation[1]=a.attcollation
                    AND opc.opcnamespace='pg_catalog'::regnamespace AND tie.opcnamespace='pg_catalog'::regnamespace AND tie.opcname='text_ops'
                    AND NOT EXISTS (SELECT 1 FROM pg_constraint k WHERE k.conindid=x.oid)) AS ordinary, opc.opcname
              FROM pg_catalog.pg_class x JOIN pg_catalog.pg_namespace n ON n.oid=x.relnamespace
              LEFT JOIN pg_catalog.pg_index i ON i.indexrelid=x.oid LEFT JOIN pg_catalog.pg_am m ON m.oid=x.relam
              LEFT JOIN pg_catalog.pg_opclass opc ON opc.oid=i.indclass[0] LEFT JOIN pg_catalog.pg_opclass tie ON tie.oid=i.indclass[1]
              LEFT JOIN pg_catalog.pg_attribute a ON a.attrelid=i.indrelid AND a.attname='record_id' AND NOT a.attisdropped
             WHERE n.nspname=? AND x.relname=?
            SQL, [$spec['namespace'], $spec['name']], false);

        if ($row === null) {
            return null;
        }

        if (! $row instanceof stdClass || $row->relkind !== 'i' || $row->ordinary !== true || $row->amname !== 'btree'
            || $row->parent_oid !== $spec['parent_oid'] || $row->owner_oid !== $spec['owner_oid'] || $row->namespace_oid !== $spec['namespace_oid']
            || $row->expression !== $spec['expression'] || $row->predicate !== $spec['predicate'] || $row->tie_key !== 'record_id'
            || $row->opcname !== match ($spec['cast']) {
                'bigint' => 'int8_ops', 'numeric' => 'numeric_ops', 'text' => 'text_ops', default => '',
            }) {
            throw new SchemaConflict('physical-index-fingerprint-conflict');
        }

        return $row;
    }

    public static function identifier(string $identifier): string
    {
        if ($identifier === '' || strlen($identifier) > 63 || str_contains($identifier, "\0")) {
            throw new SchemaConflict('invalid-owner-sql-identifier');
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
