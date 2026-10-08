#!/usr/bin/env python3
"""Lossless, digest-bound reading projection; the transfer still uses its original TSV."""
import argparse
import collections
import csv
import hashlib
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[4]
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
SOURCE = PLAN / 'artifacts/P03/module-map.tsv'
TARGET = PLAN / 'artifacts/P03/module-map.execution.json'


def project():
    rows = list(csv.DictReader(SOURCE.open(), delimiter='\t'))
    roots = sorted({(row['package'], row['path'].split('/src/')[0] + '/src',
                     row['namespace'][:len(row['namespace']) - len(str(Path(row['path'].split('/src/')[1]).parent).replace('/', chr(92))) - 1]
                     if '/' in row['path'].split('/src/')[1] else row['namespace']) for row in rows})
    assert len(roots) == len({row['package'] for row in rows})
    packages = {package: (index, path, namespace) for index, (package, path, namespace) in enumerate(roots)}
    directories = sorted({str(Path(row[key].split('/src/')[1]).parent) for row in rows for key in ('path', 'target_path')})
    roles = sorted({row['role'] for row in rows})
    rules = sorted({row['rule'] for row in rows})
    groups = collections.defaultdict(list)
    for row in rows:
        index, root, namespace = packages[row['package']]
        source = str(Path(row['path']).parent.relative_to(root))
        target = str(Path(row['target_path']).parent.relative_to(root))
        for suffix, field in ((source, 'namespace'), (target, 'target_namespace')):
            assert row[field] == namespace + (chr(92) + suffix.replace('/', chr(92)) if suffix != '.' else '')
        key = (index, directories.index(source), directories.index(target), roles.index(row['role']), rules.index(row['rule']))
        filename = Path(row['path']).name
        assert filename == Path(row['target_path']).name
        groups[key].append(row['symbol'] if filename == row['symbol'] + '.php' else [filename, row['symbol']])
    return {
        'source': 'artifacts/P03/module-map.tsv',
        'source_sha256': hashlib.sha256(SOURCE.read_bytes()).hexdigest(),
        'rows': len(rows),
        'moves': sum(row['path'] != row['target_path'] for row in rows),
        'roots': roots,
        'directories': directories,
        'roles': roles,
        'rules': rules,
        'columns': ['root_index', 'source_directory_index', 'target_directory_index', 'role_index', 'rule_index',
                    'files: symbol (=basename.php) or [basename,symbol]'],
        'groups': [[*key, files] for key, files in sorted(groups.items())],
    }, rows


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--write', action='store_true')
    args = parser.parse_args()
    projection, rows = project()
    encoded = json.dumps(projection, ensure_ascii=False, separators=(',', ':')) + '\n'
    if args.write:
        TARGET.write_text(encoded)
    assert TARGET.read_text() == encoded, 'execution projection differs from the full source'
    decoded = []
    for root_index, source_index, target_index, role_index, rule_index, files in projection['groups']:
        package, root, namespace = projection['roots'][root_index]
        source = projection['directories'][source_index]
        target = projection['directories'][target_index]
        for entry in files:
            filename, symbol = (entry + '.php', entry) if isinstance(entry, str) else entry
            decoded.append(dict(package=package, path=str(Path(root, source, filename)),
                                namespace=namespace + (chr(92) + source.replace('/', chr(92)) if source != '.' else ''),
                                symbol=symbol, role=projection['roles'][role_index], target_path=str(Path(root, target, filename)),
                                target_namespace=namespace + (chr(92) + target.replace('/', chr(92)) if target != '.' else ''),
                                rule=projection['rules'][rule_index]))
    assert sorted(decoded, key=lambda row: row['path']) == sorted(rows, key=lambda row: row['path'])
    print(f"projection OK: {len(rows)} exact rows, {projection['moves']} moves, "
          f"{len(projection['groups'])} groups, source SHA-256 unchanged")


if __name__ == '__main__':
    main()
