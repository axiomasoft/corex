#!/usr/bin/env python3
"""Verify A1–A4 repairs with isolated git/Composer/Laravel fixtures and negative controls."""
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
SCRIPTS = PLAN / 'artifacts/P02-design/scripts'
os.chdir(ROOT)


def load(name):
    spec = importlib.util.spec_from_file_location(name, SCRIPTS / (name + '.py'))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def run(args, cwd, expected=0):
    result = subprocess.run(args, cwd=cwd, capture_output=True, text=True)
    assert result.returncode == expected, (args, result.returncode, result.stdout, result.stderr)
    return {'exit': result.returncode, 'stdout': result.stdout, 'stderr': result.stderr}


def write(path, content):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content)


ids = load('p02-identifiers')
transfer = load('p02-transfer')
plan, problems = transfer.build_plan()
assert not problems
results = {}
sources = {
    'corex-auth': 'packages/access/src/CoreAuthServiceProvider.php',
    'corex-attributes': 'packages/attributes/src/AttributesServiceProvider.php',
    'corex-api': 'packages/operations/src/ApiServiceProvider.php',
    'corex-intl-search': 'packages/search-intl/src/SearchIntlServiceProvider.php',
    'corex-task-recurrence': 'packages/tasks-workflow/src/TasksWorkflowServiceProvider.php',
}

with tempfile.TemporaryDirectory(prefix='corex-p02-fix-') as directory:
    fixture = Path(directory)
    run(['git', 'init', '-q', str(fixture)], ROOT)
    (fixture / 'plans').symlink_to(ROOT / 'plans', target_is_directory=True)
    providers = []
    for number, row in enumerate(r for r in ids.rows() if r['old'] in sources):
        package = fixture / 'packages' / row['package'].removeprefix('axiomasoft/corex-')
        write(package / 'composer.json', json.dumps({'name': row['package']}, indent=4))
        write(package / 'config' / (row['old'] + '.php'), "<?php return ['audit' => 'loaded'];\n")
        line = next(line.strip() for line in (ROOT / sources[row['old']]).read_text().splitlines() if 'mergeConfigFrom' in line)
        name = 'AuditProvider' + str(number)
        provider = package / 'src/Provider.php'
        write(provider, '<?php\nclass ' + name + ' extends \\Illuminate\\Support\\ServiceProvider {\npublic function register(): void {\n' + line + '\n}\n}\n')
        providers.append((provider, name, row['new']))
    write(fixture / 'scripts/rename-completeness.json', (PLAN / 'artifacts/P02-design/facts/rename-completeness.preview.json').read_text())
    shutil.copyfile(SCRIPTS / 'rename-completeness-gate.php', fixture / 'scripts/rename-completeness-gate.php')
    # Similar basenames are outside the exact rename contract.
    boundaries = '/config/prefix-corex-auth.php /config/corex-auth.php.bak corex-authentication.php'
    write(fixture / 'boundary.txt', boundaries)
    run(['git', 'add', '.'], fixture)
    applied = run(['python3', str(SCRIPTS / 'p02-identifiers.py'), 'apply'], fixture)
    assert (fixture / 'boundary.txt').read_text() == boundaries
    checked = run(['python3', str(SCRIPTS / 'p02-identifiers.py'), 'check'], fixture)
    gate_args = [str(ROOT / 'scripts/php84'), 'php', str(fixture / 'scripts/rename-completeness-gate.php'), '--sections=identifiers']
    green_gate = run(gate_args, fixture)
    boot = "<?php\nrequire " + json.dumps(str(ROOT / 'vendor/autoload.php')) + ";\n"
    boot += "$app = new \\Illuminate\\Container\\Container;\n$app->instance('config', new \\Illuminate\\Config\\Repository);\n"
    for provider, name, key in providers:
        boot += 'require ' + json.dumps(str(provider)) + ';\n(new ' + name + "($app))->register();\n"
        boot += "if ($app->make('config')->get('" + key + ".audit') !== 'loaded') { exit(1); }\n"
    boot += 'echo "providers boot OK: 5 configs\\n";\n'
    write(fixture / 'boot.php', boot)
    booted = run([str(ROOT / 'scripts/php84'), 'php', str(fixture / 'boot.php')], fixture)
    provider = providers[0][0]
    clean = provider.read_text()
    provider.write_text(clean.replace('/config/corex-access.php', '/config/corex-auth.php'))
    red_check = run(['python3', str(SCRIPTS / 'p02-identifiers.py'), 'check'], fixture, 1)
    red_gate = run(gate_args, fixture, 1)
    assert 'corex-auth.php' in red_check['stdout'] and 'corex-auth.php' in red_gate['stderr']
    provider.write_text(clean)
    run(gate_args, fixture)
    results['A1'] = {'apply': applied, 'check': checked, 'gate': green_gate, 'boot': booted, 'negative_check': red_check, 'negative_gate': red_gate, 'exact_filename_boundaries': 'passed'}

    data = {'name': 'audit/classmap-fixture', 'autoload-dev': {
        'classmap': ['packages/modules/tests', 'tests/Support'],
        'files': ['packages/attributes/tests/helpers.php'],
    }}
    transfer.project_root_autoload(data, plan)
    assert data['autoload-dev']['classmap'] == ['packages/core/tests/Modules', 'tests/Support']
    assert data['autoload-dev']['files'] == ['packages/custom-fields/tests/helpers.php']
    write(fixture / 'packages/core/tests/Modules/Test.php', '<?php\n')
    write(fixture / 'tests/Support/Test.php', '<?php\n')
    write(fixture / 'packages/custom-fields/tests/helpers.php', '<?php\n')
    write(fixture / 'composer.json', json.dumps(data, indent=4))
    previous = Path.cwd()
    os.chdir(fixture)
    try:
        assert not transfer.root_autoload_problems(data)
        data['autoload-dev']['classmap'][0] = 'packages/modules/tests'
        negative_paths = transfer.root_autoload_problems(data)
        assert any('packages/modules/tests' in message for message in negative_paths)
        write(fixture / 'composer.json', json.dumps(data))
        bad_dump = run([str(ROOT / 'scripts/php84'), 'composer', 'dump-autoload', '--dev', '--no-scripts', '--no-plugins'], fixture, 1)
        transfer.project_root_autoload(data, plan)
        write(fixture / 'composer.json', json.dumps(data))
        good_dump = run([str(ROOT / 'scripts/php84'), 'composer', 'dump-autoload', '--dev', '--no-scripts', '--no-plugins'], fixture)
    finally:
        os.chdir(previous)
    results['A2'] = {'autoload_dev': data['autoload-dev'], 'negative_paths': negative_paths, 'negative_composer': bad_dump, 'composer': good_dump}

    moved = next(row for row in plan['extra_moves'] if row[1] == 'packages/modules/config/corex-modules.php')
    spec = (PLAN / 'phases/P02/P02.4.md').read_text()
    required = spec.split('**Required Reads:**', 1)[1].split('**Implementation Rules:**', 1)[0]
    assert 'artifacts/P02-design/facts/extra-moves.tsv' in required and moved[1] not in required
    rules = spec.split('**Implementation Rules:**', 1)[1].split('**Validation:**', 1)[0]
    assert moved[2] in rules and 'Отсутствие destination после P02.3 блокирует P02.4' in rules
    write(fixture / moved[1], '<?php return [];\n')
    run(['git', 'add', moved[1]], fixture)
    (fixture / moved[2]).parent.mkdir(parents=True, exist_ok=True)
    run(['git', 'mv', moved[1], moved[2]], fixture)
    assert (fixture / moved[2]).is_file() and not (fixture / moved[1]).exists()
    results['A3'] = {'required_read': moved[2], 'producer': 'P02.3', 'post_move_read': 'passed'}

    write(fixture / 'tests/Golden/baseline/probe.json', '{}\n')
    write(fixture / 'tests/Golden/rename-map.json', '{}\n')
    write(fixture / 'tests/Golden/Support/Probe.php', '<?php\n')
    run(['git', 'add', '.'], fixture)
    run(['git', '-c', 'user.name=Audit Fixture', '-c', 'user.email=audit@example.invalid', 'commit', '-qm', 'fixture'], fixture)
    golden_args = ['git', 'diff', '--exit-code', '--', 'tests/Golden/baseline/', 'tests/Golden/rename-map.json']
    assert ' '.join(golden_args) in (PLAN / 'phases/P02/P02.3.md').read_text()
    write(fixture / 'tests/Golden/Support/Probe.php', '<?php // projected code reference\n')
    projected_code = run(golden_args, fixture)
    run(['git', 'diff', '--exit-code', '--', 'tests/Golden/'], fixture, 1)
    write(fixture / 'tests/Golden/baseline/probe.json', '{"drift": true}\n')
    negative_baseline = run(golden_args, fixture, 1)
    write(fixture / 'tests/Golden/baseline/probe.json', '{}\n')
    write(fixture / 'tests/Golden/rename-map.json', '{"drift": true}\n')
    negative_map = run(golden_args, fixture, 1)
    write(fixture / 'tests/Golden/rename-map.json', '{}\n')
    run(golden_args, fixture)
    results['A4'] = {'projected_code_allowed': projected_code, 'negative_baseline': negative_baseline, 'negative_rename_map': negative_map}

print(json.dumps({'fixture_isolation': 'temporary git directories; product files read-only', 'result': 'passed', 'findings': results}, ensure_ascii=False, indent=2))
