#!/usr/bin/env python3
"""Independent, read-only checks of the D46 repair's production inputs and bundle boundaries."""
import copy
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[4]
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
os.chdir(ROOT)


def load(name):
    spec = importlib.util.spec_from_file_location(name, PLAN / 'artifacts/P02-design/scripts' / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


transfer = load('p02-transfer')
identifiers = load('p02-identifiers')
plan, problems = transfer.build_plan()
assert not problems, problems

# Exercise the actual pipeline order: the literal pass precedes manifest projection.
manifest = json.loads(transfer.rewrite_text(Path('composer.json').read_text(), *transfer.literal_rules(plan)))
transfer.project_root_autoload(manifest, plan)
projected_once = copy.deepcopy(manifest)
transfer.project_root_autoload(manifest, plan)
assert manifest == projected_once
exact_moves = dict(plan['moves'])
exact_moves.update({src: dst for action, src, dst in plan['extra_moves'] if action == 'move'})
deleted = {src for action, src, dst in plan['extra_moves'] if action in ('delete', 'merge-neon', 'merge-composer')}
projected_files = set()
for source in subprocess.check_output(['git', 'ls-files', '-z']).decode().split('\0'):
    if not source or source in deleted or any(source.startswith(d + '/') for d in plan['tombstones']):
        continue
    target = exact_moves.get(source, source)
    for old, new in plan['dir_renames'].items():
        if target.startswith(old + '/'):
            target = new + target[len(old):]
            break
    projected_files.add(target)
autoload_checks = []
for section in ('autoload', 'autoload-dev'):
    for kind in ('classmap', 'files'):
        for path in manifest.get(section, {}).get(kind, []):
            exists = path in projected_files or (kind == 'classmap' and any(f.startswith(path.rstrip('/') + '/') for f in projected_files))
            assert exists, (section, kind, path)
            autoload_checks.append({'section': section, 'kind': kind, 'path': path, 'projected_target_exists': exists})

# Apply the exact transformer to complete real providers, including publish destinations.
table = [r for r in identifiers.rows() if identifiers.automatic(r)]
rx = identifiers.identifier_pattern(table)
names = {r['old']: r['new'] for r in table}
files = {r['old'] + '.php': r['new'] + '.php' for r in table if r['kind'] == 'config'}
providers = {}
for path in Path('packages').glob('*/src/*ServiceProvider.php'):
    original = path.read_text()
    relevant = [old for old in files if old in original]
    if not relevant:
        continue
    changed = rx.sub(lambda m: files[m['file']] if m['file'] else names[m['id']], original)
    for old in relevant:
        assert old not in changed, (path, old)
        assert changed.count(files[old]) >= original.count(old)
    providers[str(path)] = {'old_basenames': relevant, 'all_merge_and_publish_references_projected': True}
assert len(providers) == 5

# Build the real Task input models, without writing future execution bundles.
from task import plan_bundle
bundles = {}
for item in ('P02.3', 'P02.4'):
    model = plan_bundle.model(PLAN, item)
    paths = [entry['path'] for entry in model['entries']]
    assert 'packages/modules/config/corex-modules.php' not in paths
    bundles[item] = {'entries': len(paths), 'paths': paths}

print(json.dumps({'result': 'passed', 'root_autoload_projection': autoload_checks,
                  'autoload_projection_idempotent': True, 'providers': providers,
                  'bundles': bundles,
                  'sources': {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
                              for path in (PLAN / 'artifacts/P02-design/scripts').glob('*') if path.is_file()}},
                 ensure_ascii=False, indent=2))
