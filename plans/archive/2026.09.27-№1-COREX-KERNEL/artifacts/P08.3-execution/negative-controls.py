from pathlib import Path
import hashlib
import os
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent / 'negative.txt'
log = []

def run(command):
    result = subprocess.run(command, cwd=ROOT, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, env={k: v for k, v in os.environ.items() if k != 'GOLDEN_UPDATE'})
    log.append(f'$ {" ".join(command)}\nexit={result.returncode}\n{result.stdout}')
    return result

catalogue = ROOT / 'docs/architecture/table-ownership.md'
original = catalogue.read_bytes()
try:
    text = original.decode()
    rows = text.splitlines(keepends=True)
    changed = ''.join(row for row in rows if not row.startswith('| sys_feature_flags |'))
    assert changed != text
    catalogue.write_text(changed)
    result = run(['scripts/php84', 'composer', 'schema-gate'])
    assert result.returncode == 1 and 'S9' in result.stdout and 'sys_feature_flags' in result.stdout
finally:
    catalogue.write_bytes(original)
assert catalogue.read_bytes() == original
log.append('S9 catalogue rollback: byte-identical\n')

test = ROOT / 'tests/Golden/SchemaSqliteGoldenTest.php'
original = test.read_bytes()
try:
    with tempfile.TemporaryDirectory(prefix='corex-schema-negative-') as directory:
        fixture = ROOT / 'tests/fixtures/schema-gate/golden/2026_10_01_990010_add_schema_probe.php.fixture'
        Path(directory, fixture.name.removesuffix('.fixture')).write_bytes(fixture.read_bytes())
        text = original.decode()
        anchor = '        $ids = array_map('
        assert text.count(anchor) == 1
        inject = f"        $selection[] = new SchemaSet('fixture/golden-probe', 'tenant', SchemaScope::Tenant, '{directory}', 1, ['pgsql', 'sqlite']);\n"
        test.write_text(text.replace(anchor, inject + anchor))
        result = run(['scripts/php84', 'vendor/bin/pest', 'tests/Golden/SchemaSqliteGoldenTest.php', '--compact'])
        assert result.returncode == 1 and 'Schema drift: crm_sources' in result.stdout
finally:
    test.write_bytes(original)
assert test.read_bytes() == original
log.append('Golden fixture rollback: byte-identical\n')
result = run(['scripts/php84', 'composer', 'schema-gate'])
assert result.returncode == 0
result = run(['scripts/php84', 'vendor/bin/pest', 'tests/Golden/SchemaSqliteGoldenTest.php', '--compact'])
assert result.returncode == 0
OUT.write_text('\n'.join(log))
print('negative controls GREEN: S9 RED, Golden-index RED, both restored and GREEN')
