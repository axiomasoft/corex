<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

require_once dirname(__DIR__, 2).'/scripts/schema-gate.php';

/** @return list<array{code: string, file: string, line: int, message: string}> */
function schemaGateProbe(string $source, string $filename = '2026_10_01_010010_create_sys_gate_probe.php', int $version = 1, string $scope = 'tenant', string $catalogue = 'sys_gate_probe', array $allowlist = []): array
{
    $root = sys_get_temp_dir().'/corex-schema-gate-'.bin2hex(random_bytes(12));
    $path = 'packages/core/database/migrations/tenant';
    $filesystem = new Filesystem;

    try {
        $filesystem->makeDirectory($root.'/'.$path, 0700, true);
        $filesystem->makeDirectory($root.'/docs/architecture', 0700, true);
        file_put_contents($root.'/'.$path.'/'.$filename, $source);
        file_put_contents($root.'/docs/architecture/table-ownership.md', "| {$catalogue} | axiomasoft/corex | tenant |\n");
        $manifest = ['packages' => ['axiomasoft/corex' => '01'], 'sets' => [[
            'id' => 'axiomasoft/corex:tenant', 'package' => 'axiomasoft/corex', 'name' => 'tenant', 'scope' => $scope,
            'path' => str_starts_with($filename, 'unregistered') ? $path.'/missing' : $path, 'version' => $version,
            'drivers' => ['pgsql', 'sqlite'], 'lifecycle' => 'platform', 'requires' => [], 'connection' => null,
        ]]];

        return new SchemaGate($root, $manifest, $allowlist)->run();
    } finally {
        $filesystem->deleteDirectory($root);
    }
}

it('accepts a literal baseline and ignores impure down guards and comments', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    expect(schemaGateProbe($source))->toBe([]);
});

it('rejects each schema rule with its own diagnostic code', function (string $code): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/'.$code.'/migration.php.fixture');
    $hits = schemaGateProbe($source,
        filename: match ($code) {
            'S1' => 'unregistered.php', 'S2' => '2026_10_01_020010_create_sys_gate_probe.php', default => '2026_10_01_010010_create_sys_gate_probe.php',
        },
        version: $code === 'S8' ? 2 : 1, catalogue: $code === 'S9' ? 'other_table' : 'sys_gate_probe');
    expect(array_values(array_unique(array_column($hits, 'code'))))->toBe([$code]);
    foreach ($hits as $hit) {
        expect($hit['line'])->toBeGreaterThan(0)->and($hit['file'])->toContain('packages/core/database/migrations/tenant');
    }
})->with(['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8', 'S9']);

it('enforces purity enum and ownership even for malformed filenames', function (string $code): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/'.$code.'/migration.php.fixture');
    $hits = schemaGateProbe($source, filename: 'old_migration.php', catalogue: $code === 'S9' ? 'other_table' : 'sys_gate_probe');
    $codes = array_values(array_unique(['S2', $code, 'S8']));
    sort($codes);
    expect(array_values(array_unique(array_column($hits, 'code'))))->toBe($codes);
})->with(['S5', 'S7', 'S9']);

it('enforces baseline shape rules for every package even with legacy filenames and versions', function (string $code): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/'.$code.'/migration.php.fixture');
    $codes = array_values(array_unique(['S2', $code, 'S8']));
    sort($codes);
    expect(array_values(array_unique(array_column(schemaGateProbe($source, filename: 'old_migration.php', version: 7), 'code'))))->toBe($codes);
})->with(['S2', 'S3', 'S4', 'S6', 'S8']);

it('admits only finite table column integer exceptions', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/S6/migration.php.fixture');
    expect(schemaGateProbe($source, allowlist: ['sys_gate_probe.attempts' => 'bounded test value']))->toBe([])
        ->and(array_column(schemaGateProbe($source, allowlist: ['other_table.attempts' => 'wrong table']), 'code'))->toBe(['S6']);
});

it('checks scope as well as package ownership', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    expect(array_unique(array_column(schemaGateProbe($source, scope: 'central'), 'code')))->toBe(['S9']);
});

it('rejects nonliteral and long object names', function (string $replacement): void {
    $source = str_replace("'sys_gate_probe_pk'", $replacement, (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture'));
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe(['S4']);
})->with(['dynamic' => '$name', 'long' => "'sys_gate_probe_".str_repeat('x', 64)."_pk'"]);

it('detects whitespace separated calls and ignores SQL-looking comments', function (): void {
    $source = str_replace('$'."table->uuid('id')", '$'."table -> enum ( 'id', ['yes', 'no'] )", (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture'));
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe(['S5']);
});

it('checks purity in invoked helpers and raw data SQL', function (string $change): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');

    if ($change === 'helper') {
        $source = str_replace('        Schema::create', '        $'."this->build();\n        Schema::create", $source);
        $source = str_replace('    public function down()', "    private function build(): void { config('schema'); }\n\n    public function down()", $source);
    } else {
        $source = str_replace('        Schema::create', "        \\Illuminate\\Support\\Facades\\DB::statement('UPDATE sys_gate_probe SET id = id');\n        Schema::create", $source);
    }
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe(['S7']);
})->with(['helper', 'sql']);

it('accepts named Blueprint arguments and fluent FK names', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    $source = str_replace("->primary(['id'], 'sys_gate_probe_pk')", "->primary(['id'], name: 'sys_gate_probe_pk')", $source);
    $source = str_replace('$'."table->uuid('id');", '$'."table->uuid('id');\n            $"."table->foreign(['id'])->references('id')->on('sys_gate_probe')->name('sys_gate_probe_self_fk');", $source);
    expect(schemaGateProbe($source))->toBe([]);
});

it('checks ownership for helpers and every callback syntax', function (string $syntax): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');

    if ($syntax === 'helper') {
        $source = str_replace('    public function up(): void', '    public function up(): void { $'."this->createTables(); }\n\n    private function createTables(): void", $source);
    } elseif ($syntax === 'arrow') {
        $source = preg_replace('/function \(Blueprint \$table\): void \{.*?\}/s', 'fn (Blueprint $table) => $table->uuid(\'id\')', $source, 1);
    } else {
        $source = preg_replace('/function \(Blueprint \$table\): void \{.*?\}/s', '$callback', $source, 1);
    }
    expect(array_values(array_unique(array_column(schemaGateProbe($source, catalogue: 'other_table'), 'code'))))->toBe(['S9']);
})->with(['helper', 'arrow', 'variable']);

it('applies baseline rules inside invoked helpers', function (string $code): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/'.$code.'/migration.php.fixture');
    $source = str_replace('    public function up(): void', '    public function up(): void { $'."this->createTables(); }\n\n    private function createTables(): void", $source);
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe([$code]);
})->with(['S3', 'S4', 'S6']);

it('rejects unnamed fluent foreign keys and admits explicit names', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    $source = str_replace('$'."table->uuid('id');", '$'."table->foreignUuid('id')->references('id')->on('sys_gate_probe');", $source);
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe(['S3']);
    $named = str_replace("->on('sys_gate_probe');", "->on('sys_gate_probe')->name('sys_gate_probe_self_fk');", $source);
    expect(schemaGateProbe($named))->toBe([]);
});

it('rejects a nonbaseline date independently of filename shape', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    expect(array_values(array_unique(array_column(schemaGateProbe($source, filename: '2026_10_02_010010_create_sys_gate_probe.php'), 'code'))))->toBe(['S8']);
});

it('rejects duplicate object names in one scope', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    $source = str_replace('$'."table->primary(['id'], 'sys_gate_probe_pk');", '$'."table->primary(['id'], 'sys_gate_probe_pk');\n            $"."table->index(['id'], 'sys_gate_probe_pk');", $source);
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe(['S4']);
});

it('validates the target of a constraint rename rather than its old reference', function (string $target, array $codes): void {
    $source = (string) file_get_contents(__DIR__.'/../fixtures/schema-gate/valid.php.fixture');
    $rename = "        \\Illuminate\\Support\\Facades\\DB::statement('ALTER TABLE sys_gate_probe RENAME CONSTRAINT sys_gate_probe_pkey TO ".$target."');\n";
    $source = str_replace('        });', '        });'."\n".$rename, $source);
    expect(array_values(array_unique(array_column(schemaGateProbe($source), 'code'))))->toBe($codes);
})->with([
    'valid' => ['sys_gate_probe_pk', []],
    'wrong suffix' => ['sys_gate_probe_bad', ['S4']],
    'wrong table' => ['other_table_pk', ['S4']],
    'too long' => ['sys_gate_probe_'.str_repeat('x', 64).'_pk', ['S4']],
]);

/** @param array<string, string> $allowlist
 * @return list<array{code: string, file: string, line: int, message: string}> */
function schemaGateRawProbe(string $sql, array $allowlist = []): array
{
    return schemaGateProbe('<?php return new class { public function up(): void { DB::statement('.var_export($sql, true).'); } };', allowlist: $allowlist);
}

it('rejects raw narrow integer column DDL including aliases and multiple actions', function (string $sql): void {
    $hits = schemaGateRawProbe($sql);
    expect(array_unique(array_column($hits, 'code')))->toBe(['S6'])
        ->and(implode(' ', array_column($hits, 'message')))->toContain('sys_gate_probe.attempts');
})->with([
    'CREATE TABLE sys_gate_probe (id uuid, attempts integer)',
    'CREATE TABLE sys_gate_probe (attempts int4)',
    'CREATE TABLE sys_gate_probe (attempts smallint)',
    'CREATE TABLE sys_gate_probe (attempts serial)',
    'CREATE TABLE sys_gate_probe (attempts int2)',
    'ALTER TABLE sys_gate_probe ADD attempts integer',
    'ALTER TABLE sys_gate_probe ADD COLUMN IF NOT EXISTS attempts int',
    'ALTER TABLE sys_gate_probe ALTER COLUMN attempts TYPE integer USING attempts::integer',
    'ALTER TABLE ONLY sys_gate_probe ALTER attempts SET DATA TYPE smallint',
    'ALTER TABLE public."sys_gate_probe" ADD COLUMN "attempts" int4',
    'ALTER TABLE sys_gate_probe ADD COLUMN safe bigint, ADD COLUMN attempts integer',
    'ALTER TABLE sys_gate_probe ADD CONSTRAINT sys_gate_probe_ck CHECK (true), ADD attempts integer',
    'ALTER /* comment */ TABLE sys_gate_probe ADD attempts integer',
    'CREATE TABLE sys_gate_probe (safe numeric(10, 2) DEFAULT 1, attempts integer); ALTER TABLE sys_gate_probe ADD x bigint',
]);

it('accepts raw bigint DDL and ignores integer words in expressions comments and strings', function (string $sql): void {
    expect(schemaGateRawProbe($sql))->toBe([]);
})->with([
    'CREATE TABLE sys_gate_probe (id uuid, attempts bigint, note text DEFAULT \'integer; int4, serial\')',
    'ALTER TABLE sys_gate_probe ADD COLUMN attempts int8',
    'ALTER TABLE sys_gate_probe ADD attempts bigserial',
    'ALTER TABLE sys_gate_probe ALTER attempts TYPE bigint USING attempts::integer',
    'ALTER TABLE sys_gate_probe ADD attempts bigint /* integer */, ADD cost numeric(10, 2)',
]);

it('applies raw integer exceptions to the exact table and column only', function (): void {
    foreach (['CREATE TABLE sys_gate_probe (attempts integer)', 'ALTER TABLE sys_gate_probe ADD COLUMN attempts integer', 'ALTER TABLE public."sys_gate_probe" ALTER COLUMN "attempts" TYPE int4'] as $sql) {
        expect(schemaGateRawProbe($sql, ['sys_gate_probe.attempts' => 'bounded fixture']))->toBe([]);
        foreach (['other_table.attempts', 'sys_gate_probe.other', '*.attempts', 'sys_gate_probe.*'] as $key) {
            expect(array_column(schemaGateRawProbe($sql, [$key => 'wrong identity']), 'code'))->toBe(['S6']);
        }
    }
    expect(array_column(schemaGateRawProbe('ALTER TABLE sys_gate_probe ADD COLUMN "Attempts" integer', ['sys_gate_probe.attempts' => 'case sensitive']), 'code'))->toBe(['S6']);
});

it('fails explicitly for unsupported raw column definitions', function (string $sql): void {
    $hits = schemaGateRawProbe($sql);
    expect(array_unique(array_column($hits, 'code')))->toBe(['S6'])
        ->and(implode(' ', array_column($hits, 'message')))->toContain('cannot verify raw column DDL');
})->with([
    'CREATE TABLE sys_gate_probe (attempts custom_integer_domain)',
    'CREATE TABLE sys_gate_probe (LIKE existing INCLUDING ALL)',
    'CREATE TABLE sys_gate_probe AS SELECT 1 AS attempts',
    'CREATE TABLE sys_gate_probe (attempts pg_catalog.int4)',
    'ALTER TABLE sys_gate_probe MODIFY COLUMN attempts integer',
    'ALTER TABLE sys_gate_probe ADD COLUMN attempts',
    'ALTER TABLE sys_gate_probe ADD COLUMN attempts %s',
    'ALTER TABLE other_schema.sys_gate_probe ADD attempts integer',
    'ALTER TABLE sys_gate_probe ADD COLUMN attempts bigint,',
]);

it('inspects heredoc and invoked helper raw column DDL and rejects dynamic types', function (): void {
    $source = <<<'PHP'
        <?php return new class {
            public function up(): void { $this->columns(); }
            private function columns(): void {
                DB::unprepared(<<<'SQL'
                ALTER TABLE sys_gate_probe ADD COLUMN attempts integer
                SQL);
            }
        };
        PHP;
    expect(array_column(schemaGateProbe($source), 'code'))->toBe(['S6']);
    foreach ([
        'DB::statement("ALTER TABLE sys_gate_probe ADD attempts {$type}");',
        'DB::statement(\'CREATE\' . \' TABLE sys_gate_probe (attempts integer)\');',
        'DB::statement($sql);',
        'DB::statement(sprintf(\'ALTER TABLE sys_gate_probe ADD attempts %s\', $type));',
    ] as $statement) {
        expect(array_column(schemaGateProbe('<?php return new class { public function up(): void { '.$statement.' } };'), 'code'))->toContain('S6');
    }
});

it('checks raw column DDL through all SQL execution APIs and unknown literal wrappers', function (string $method): void {
    $source = '<?php return new class { public function up(): void { DB::'.$method.'("ALTER TABLE sys_gate_probe ADD attempts integer"); } };';
    expect(array_column(schemaGateProbe($source), 'code'))->toContain('S6');
})->with(['select', 'selectOne', 'affectingStatement', 'scalar', 'cursor', 'exec', 'query', 'customSqlExecutor']);

it('does not resolve earlier SQL or table identifiers using a later reassignment', function (): void {
    foreach ([
        '$sql = "ALTER TABLE sys_gate_probe ADD attempts integer"; DB::statement($sql); $sql = "ALTER TABLE sys_gate_probe ADD attempts bigint"; DB::statement($sql);',
        '$sql = "ALTER TABLE sys_gate_probe ADD safe bigint"; $sql .= ", ADD attempts integer"; DB::statement($sql);',
        '$table = "sys_gate_probe"; DB::statement("ALTER TABLE {$table} ADD attempts integer"); $table = "other_table";',
    ] as $body) {
        expect(array_column(schemaGateProbe('<?php return new class { public function up(): void { '.$body.' } };', allowlist: ['other_table.attempts' => 'wrong table']), 'code'))->toContain('S6');
    }
    $source = '<?php return new class { public function up(): void { $table = "sys_gate_probe"; DB::statement("ALTER TABLE {$table} ADD attempts integer"); } };';
    expect(schemaGateProbe($source, allowlist: ['sys_gate_probe.attempts' => 'exact fixture']))->toBe([]);
});

it('fails explicitly for procedural and unsupported wrappers that conceal column DDL', function (string $sql): void {
    $hits = schemaGateRawProbe($sql);
    expect(array_unique(array_column($hits, 'code')))->toBe(['S6'])
        ->and(implode(' ', array_column($hits, 'message')))->toContain('cannot verify raw column DDL');
})->with([
    "DO 'BEGIN CREATE TABLE sys_gate_probe (id uuid, attempts integer NOT NULL); END'",
    'DO $$ BEGIN ALTER TABLE sys_gate_probe ADD attempts integer; END $$',
    'DO $$ BEGIN EXECUTE ddl; END $$',
    'CALL install_columns()',
    'EXECUTE prepared_ddl',
    "SELECT execute_ddl('ALTER TABLE sys_gate_probe ADD attempts integer')",
    "CREATE FUNCTION add_columns() RETURNS void AS 'BEGIN ALTER TABLE sys_gate_probe ADD attempts integer; END' LANGUAGE plpgsql",
]);

it('checks concatenated column DDL through PDO Laravel and other literal SQL methods', function (string $method): void {
    $executor = $method === 'prepare' ? 'Schema::getConnection()->getPdo()' : 'Schema::getConnection()';
    $source = '<?php return new class { public function up(): void { '.$executor.'->'.$method.'(\'ALTER \' . \'TABLE sys_gate_probe ADD attempts integer\'); } };';
    expect(array_column(schemaGateProbe($source), 'code'))->toContain('S6');
    expect(schemaGateProbe(str_replace('attempts integer', 'attempts bigint', $source)))->toBe([]);
    expect(schemaGateProbe($source, allowlist: ['sys_gate_probe.attempts' => 'bounded fixture']))->toBe([]);
    expect(array_column(schemaGateProbe($source, allowlist: ['other_table.attempts' => 'wrong identity']), 'code'))->toContain('S6');
})->with(['prepare', 'selectFromWriteConnection', 'selectResultSets', 'customSqlExecutor']);

it('keeps ordinary Blueprint names out of raw procedural SQL parsing', function (): void {
    $source = '<?php return new class { public function up(): void { Schema::create("sys_gate_probe", function ($table) { $table->uuid("call"); $table->string("execute"); $table->bigInteger("alter"); }); } };';
    expect(schemaGateProbe($source))->toBe([]);
});

it('fails explicitly when a computed ALTER suffix can conceal column actions', function (string $prefix): void {
    $source = '<?php return new class { public function up(): void { $ddl = $this->columnClause(); DB::statement('.var_export($prefix, true).' . $ddl); } private function columnClause(): string { return "sys_gate_probe ADD attempts integer"; } };';
    $hits = schemaGateProbe($source);
    expect(array_column($hits, 'code'))->toContain('S6')
        ->and(implode(' ', array_column($hits, 'message')))->toContain('cannot verify raw column DDL');
})->with(['ALTER TABLE ', 'ALTER ']);
