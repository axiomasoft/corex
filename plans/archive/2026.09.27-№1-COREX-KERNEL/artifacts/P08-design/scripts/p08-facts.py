#!/usr/bin/env python3
"""Reproduces artifacts/P08-design/facts/* from the repository root. Read-only.

Usage: python3 plans/2026.09.27-№1-COREX-KERNEL/artifacts/P08-design/scripts/p08-facts.py [OUT_DIR]

Regex inventory of shipped migrations (git-tracked `packages/*/database/migrations/**`,
`products/*/database/migrations/**`). It is a map for the designer/executor, not a gate:
acceptance uses the DB-introspecting schema gate built in P08.
"""
import json, os, re, subprocess, sys
from collections import defaultdict

root = subprocess.check_output(['git', 'rev-parse', '--show-toplevel'], text=True).strip()
os.chdir(root)
out = sys.argv[1] if len(sys.argv) > 1 else os.path.join(
    root, 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P08-design/facts')
os.makedirs(out, exist_ok=True)
head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()

files = [f for f in subprocess.check_output(
    ['git', 'ls-files', 'packages/*/database/migrations/**', 'products/*/database/migrations/**'],
    text=True).split() if f.endswith('.php')]

RX = {
    'create': re.compile(r"Schema::(?:connection\([^)]*\)->)?create\(\s*([^,]+),"),
    'alter': re.compile(r"Schema::(?:connection\([^)]*\)->)?table\(\s*([^,]+),"),
    'enum': re.compile(r"->enum\(|CREATE TYPE|\benum\b\s*\(", re.I),
    'raw': re.compile(r"DB::(?:connection\([^)]*\)->)?(?:statement|unprepared)\("),
    'unnamed_idx': re.compile(r"->(index|unique|primary|spatialIndex|fullText)\(\s*(\[[^\]]*\]|'[^']*')\s*\)"),
    'chained_unnamed': re.compile(r"\)->(unique|index)\(\s*\)"),
    'foreign_unnamed': re.compile(r"->foreign\(\s*(\[[^\]]*\]|'[^']*')\s*\)"),
    'constrained': re.compile(r"->(?:foreignId|foreignUuid|foreignUlid)\([^)]*\)[^;]*->constrained\("),
    'morphs': re.compile(r"->(nullable)?(u?u?id|ul?id)?[mM]orphs\("),
    'int32': re.compile(r"->(unsignedInteger|integer|increments|smallInteger|unsignedSmallInteger|tinyInteger|unsignedTinyInteger|mediumInteger)\(\s*'([^']+)'"),
    'driver': re.compile(r"getDriverName\(\)|driver\s*[!=]==|'pgsql'|'sqlite'"),
    'config_table': re.compile(r"config\(\s*'([^']+)'"),
    'down': re.compile(r"function down\("),
    'timestamp_named': re.compile(r"^(\d{4}_\d{2}_\d{2}_\d{6})_"),
}

def pkg_of(f):
    p = f.split('/')
    return p[0] + '/' + p[1]

def scope_of(f):
    rel = f.split('/database/migrations/', 1)[1]
    return rel.split('/')[0] if '/' in rel else '(root)'

rows = []
per_pkg = defaultdict(lambda: defaultdict(int))
for f in sorted(files):
    src = open(f, encoding='utf-8').read()
    r = {
        'file': f, 'package': pkg_of(f), 'scope_dir': scope_of(f),
        'timestamped': bool(RX['timestamp_named'].match(os.path.basename(f))),
        'creates': [m.strip() for m in RX['create'].findall(src)],
        'alters': [m.strip() for m in RX['alter'].findall(src)],
        'enum': len(RX['enum'].findall(src)),
        'raw_sql': len(RX['raw'].findall(src)),
        'unnamed_index': len(RX['unnamed_idx'].findall(src)) + len(RX['chained_unnamed'].findall(src)),
        'unnamed_fk': len(RX['foreign_unnamed'].findall(src)) + len(RX['constrained'].findall(src)),
        'morphs': len(RX['morphs'].findall(src)),
        'int32_cols': sorted({m[1] for m in RX['int32'].findall(src)}),
        'driver_branch': len(RX['driver'].findall(src)),
        'config_tables': sorted(set(RX['config_table'].findall(src))),
        'has_down': bool(RX['down'].search(src)),
    }
    rows.append(r)
    pp = per_pkg[r['package']]
    pp['files'] += 1
    for k in ('enum', 'raw_sql', 'unnamed_index', 'unnamed_fk', 'morphs', 'driver_branch'):
        pp[k] += r[k]
    pp['alter_files'] += 1 if r['alters'] else 0
    pp['int32_cols'] += len(r['int32_cols'])

with open(os.path.join(out, 'migrations.tsv'), 'w') as fh:
    fh.write(f'# base: {head}\n')
    fh.write('file\tpackage\tscope_dir\ttimestamped\tcreates\talters\tenum\traw_sql\tunnamed_index\tunnamed_fk\tmorphs\tint32_cols\tdriver_branch\tconfig_tables\thas_down\n')
    for r in rows:
        fh.write('\t'.join(str(x) if not isinstance(x, list) else ','.join(x) for x in [
            r['file'], r['package'], r['scope_dir'], r['timestamped'], r['creates'], r['alters'], r['enum'],
            r['raw_sql'], r['unnamed_index'], r['unnamed_fk'], r['morphs'], r['int32_cols'],
            r['driver_branch'], r['config_tables'], r['has_down']]) + '\n')

with open(os.path.join(out, 'summary.tsv'), 'w') as fh:
    fh.write(f'# base: {head}\n')
    keys = ['files', 'alter_files', 'enum', 'raw_sql', 'unnamed_index', 'unnamed_fk', 'morphs', 'int32_cols', 'driver_branch']
    fh.write('package\t' + '\t'.join(keys) + '\n')
    for p in sorted(per_pkg):
        fh.write(p + '\t' + '\t'.join(str(per_pkg[p][k]) for k in keys) + '\n')

def grep(args):
    try:
        return subprocess.check_output(['git', 'grep', '-n', '-E'] + args, text=True)
    except subprocess.CalledProcessError:
        return ''

with open(os.path.join(out, 'registration.txt'), 'w') as fh:
    fh.write(f'# base: {head}\n## provider migration registration (publish / load)\n')
    fh.write(grep([r"loadMigrationsFrom|publishesMigrations|database/migrations|-migrations", '--', 'packages/*/src/**', 'products/*/src/**']))
    fh.write('\n## runtime migrate calls\n')
    fh.write(grep([r"Artisan::call\('migrate|'migrate'|->call\('migrate|migrator", '--', 'packages/*/src/**', 'products/*/src/**']))

with open(os.path.join(out, 'test-migration-refs.txt'), 'w') as fh:
    fh.write(f'# base: {head}\n## tests that name migration paths/files (must follow the baseline)\n')
    fh.write(grep([r"database/migrations|loadMigrationsFrom|'--path'|migrate:fresh|RefreshDatabase|DatabaseMigrations", '--',
                   'packages/*/tests/**', 'products/*/tests/**', 'tests/**', ':!**/vendor/**']))

with open(os.path.join(out, 'schema-table-config.txt'), 'w') as fh:
    fh.write(f'# base: {head}\n## configurable table names (config keys read by migrations)\n')
    for r in rows:
        if r['config_tables']:
            fh.write(f"{r['file']}\t{','.join(r['config_tables'])}\n")
    fh.write('\n## table-name config entries in package config files\n')
    fh.write(grep([r"'(table|tables|table_names|.*_table)'\s*=>", '--', 'packages/*/config/**', 'products/*/config/**']))

print(f'{len(rows)} migrations → {out}')
