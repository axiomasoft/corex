#!/usr/bin/env python3
"""Reproduce P02 design findings in disposable fixtures; product files are read-only."""
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[4]
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
PREIMAGE = PLAN / 'artifacts/P02-design-audit/preimage'
SCRIPTS = PREIMAGE / 'artifacts/P02-design/scripts'
os.chdir(ROOT)


def load(name):
    spec = importlib.util.spec_from_file_location(name, SCRIPTS / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def run(args, cwd):
    result = subprocess.run(args, cwd=cwd, capture_output=True, text=True)
    return {'exit': result.returncode, 'stdout': result.stdout, 'stderr': result.stderr}


ids = load('p02-identifiers')
transfer = load('p02-transfer')
rules = {r['old']: r['new'] for r in ids.rows() if ids.automatic(r)}
rx = re.compile(ids.BEFORE + '(' + '|'.join(re.escape(k) for k in sorted(rules, key=len, reverse=True)) + ')' + ids.AFTER)
plan, problems = transfer.build_plan()
assert not problems
literal_rules = transfer.literal_rules(plan)
results = {}

with tempfile.TemporaryDirectory(prefix='corex-p02-audit-') as directory:
    fixture = Path(directory)
    subprocess.run(['git', 'init', '-q', str(fixture)], check=True)
    (fixture / 'plans').symlink_to(ROOT / 'plans', target_is_directory=True)
    scripts = fixture / 'scripts'
    scripts.mkdir()
    (fixture / 'packages/access/src').mkdir(parents=True)
    before = next(line.strip() for line in (ROOT / 'packages/access/src/CoreAuthServiceProvider.php').read_text().splitlines() if 'mergeConfigFrom' in line)
    source = fixture / 'packages/access/src/Provider.php'
    source.write_text('<?php\n' + before + '\n')
    for row in ids.rows():
        if row['kind'] == 'config' and re.match(r'file (config/\S+\.php)', row['note']):
            package_name = row['package'].removeprefix('axiomasoft/corex-')
            package = fixture / 'packages' / package_name
            (package / 'config').mkdir(parents=True, exist_ok=True)
            (package / 'composer.json').write_text(json.dumps({'name': row['package']}, indent=4))
            (package / 'config' / (row['old'] + '.php')).write_text('<?php return [];\n')
    (scripts / 'rename-completeness.json').write_bytes((PLAN / 'artifacts/P02-design/facts/rename-completeness.preview.json').read_bytes())
    shutil.copyfile(SCRIPTS / 'rename-completeness-gate.php', scripts / 'rename-completeness-gate.php')
    subprocess.run(['git', '-C', str(fixture), 'add', '.'], check=True)
    applied = run(['python3', str(SCRIPTS / 'p02-identifiers.py'), 'apply'], fixture)
    assert applied['exit'] == 0, applied
    after = source.read_text().splitlines()[1]
    assert not (fixture / 'packages/access/config/corex-auth.php').exists()
    assert (fixture / 'packages/access/config/corex-access.php').is_file()
    checked = run(['python3', str(SCRIPTS / 'p02-identifiers.py'), 'check'], fixture)
    gate = run([str(ROOT / 'scripts/php84'), 'php', str(scripts / 'rename-completeness-gate.php'), '--sections=identifiers'], fixture)
    assert 'config/corex-auth.php' in after and checked['exit'] == 0 and gate['exit'] == 0
    results['A1'] = {'before': before, 'after': after, 'old_config_path_survives': True, 'identifier_apply': applied, 'identifier_check': checked, 'completeness_gate': gate}

    projected = json.loads(transfer.rewrite_text((ROOT / 'composer.json').read_text(), *literal_rules))
    classmap = [path for path in projected['autoload-dev']['classmap'] if path.startswith('packages/modules/')]
    assert classmap == ['packages/modules/tests']
    (fixture / 'composer.json').write_text(json.dumps({'name': 'audit/classmap-fixture', 'autoload-dev': {'classmap': classmap}}))
    dumped = run([str(ROOT / 'scripts/php84'), 'composer', 'dump-autoload', '--dev', '--no-scripts', '--no-plugins'], fixture)
    assert dumped['exit'] != 0 and 'packages/modules/tests' in (dumped['stderr'] + dumped['stdout']), dumped
    results['A2'] = {'projected_classmap': classmap, 'dump_autoload': dumped, 'root_manifests_writer_updates': 'autoload-dev.psr-4 only; classmap remains unchanged'}

    moved = next(row for row in plan['extra_moves'] if row[1] == 'packages/modules/config/corex-modules.php')
    spec = (PREIMAGE / 'phases/P02/P02.4.md').read_text()
    required = spec.split('**Required Reads:**', 1)[1].split('**Implementation Rules:**', 1)[0]
    assert moved[1] in required
    results['A3'] = {'P02.3_move': list(moved), 'P02.4_required_read': moved[1], 'old_path_absent_after_git_mv': True}

    support = ROOT / 'tests/Golden/Support/GoldenSnapshot.php'
    original = support.read_text()
    target = fixture / 'tests/Golden/Support/GoldenSnapshot.php'
    target.parent.mkdir(parents=True)
    target.write_text(original)
    subprocess.run(['git', '-C', str(fixture), 'add', '.'], check=True)
    subprocess.run(['git', '-C', str(fixture), '-c', 'user.name=Audit Fixture', '-c', 'user.email=audit@example.invalid', 'commit', '-qm', 'fixture'], check=True)
    config = fixture / 'rector.php'
    config.write_text("<?php\nuse Rector\\Config\\RectorConfig;\nuse Rector\\Renaming\\Rector\\Name\\RenameClassRector;\nreturn RectorConfig::configure()->withPaths([__DIR__.'/tests/Golden/Support/GoldenSnapshot.php'])->withConfiguredRule(RenameClassRector::class, ['CoreX\\\\Events\\\\DomainEvent' => 'CoreX\\\\Messaging\\\\Events\\\\DomainEvent']);\n")
    rector = run([str(ROOT / 'scripts/php84'), str(ROOT / 'vendor/bin/rector'), 'process', '--config', str(config), '--no-progress-bar', '--no-diffs'], fixture)
    assert rector['exit'] == 0, rector
    assert target.read_text() != original
    diff = run(['git', 'diff', '--exit-code', '--', 'tests/Golden/'], fixture)
    assert diff['exit'] == 1
    results['A4'] = {'changed_by_required_rector_rule': 'tests/Golden/Support/GoldenSnapshot.php', 'rector': rector, 'frozen_baseline_changed': False, 'required_validation': diff}

print(json.dumps({'fixture_isolation': 'temporary directories only; no product mutation', 'findings': results}, ensure_ascii=False, indent=2))
