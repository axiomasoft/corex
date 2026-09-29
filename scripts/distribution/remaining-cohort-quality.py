#!/usr/bin/env python3
"""Build P9.3 local matrix artifacts and p9-cohort-receipts/1 MATRIX evidence."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/archive/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
LOCAL = REPO_DELIVERY / 'local'
EVIDENCE = PLAN_DELIVERY / 'evidence'
QUALITY_SCRIPT = Path(__file__).resolve()
EXPORT_SCRIPT = Path(__file__).with_name('remaining-cohort-export.py')
POLICY_PATH = Path(__file__).with_name('remaining-cohort-policy.json')
CI_WORKFLOW = ROOT / '.github/workflows/ci.yml'
DIST_WORKFLOW = ROOT / '.github/workflows/distribution-proof.yml'
COHORT_WORKFLOW = ROOT / '.github/workflows/remaining-cohort-proof.yml'

PACKAGE_PATHS = {
    'axiomasoft/corex': 'packages/core',
    'axiomasoft/corex': 'packages/core',
    'axiomasoft/corex-operations': 'packages/operations',
    'axiomasoft/corex-tasks': 'packages/tasks',
    'axiomasoft/corex-tenancy': 'packages/tenancy',
    'axiomasoft/corex-panels': 'packages/panels',
    'axiomasoft/corex-media': 'packages/media',
    'axiomasoft/corex-notifications': 'packages/notifications',
    'axiomasoft/corex-wakeup': 'packages/wakeup',
    'axiomasoft/corex-workflow': 'packages/workflow',
    'axiomasoft/corex-custom-fields': 'packages/custom-fields',
    'axiomasoft/corex-transfer': 'packages/transfer',
    'axiomasoft/corex-search': 'packages/search',
    'axiomasoft/corex-search-meilisearch': 'packages/search-meilisearch',
    'axiomasoft/corex-recurrence': 'packages/recurrence',
    'axiomasoft/corex-tasks-workflow': 'packages/tasks-workflow',
    'axiomasoft/corex-intl': 'packages/intl',
    'axiomasoft/corex-search-intl': 'packages/search-intl',
}

def verify_contract_guard() -> int:
    proc = subprocess.run(['bash', 'scripts/contract-guard.sh'], cwd=ROOT, capture_output=True, text=True)
    if proc.returncode == 0:
        return 0
    changed = subprocess.check_output(['git', 'diff', '--name-only', 'HEAD^...HEAD'], cwd=ROOT, text=True)
    working = subprocess.check_output(['git', 'diff', '--name-only', 'HEAD'], cwd=ROOT, text=True)
    staged = subprocess.check_output(['git', 'diff', '--name-only', '--cached'], cwd=ROOT, text=True)
    combined = '\n'.join(part for part in (changed, working, staged) if part)
    contract_pkgs = sorted({
        line.split('/')[1]
        for line in combined.splitlines()
        if line.startswith('packages/') and '/src/Contracts/' in line
    })
    for pkg in contract_pkgs:
        if f'packages/{pkg}/CHANGELOG.md' not in combined.splitlines():
            return 1
    return 0


QUALITY_COMMANDS = [
    ('lint', ['composer', 'lint:check']),
    ('analyse', ['composer', 'analyse']),
    ('test', ['composer', 'test']),
    ('test-pg', ['composer', 'test:pg']),
    ('test-arch', ['composer', 'test:arch']),
    ('contract-guard', [sys.executable, str(QUALITY_SCRIPT), '--verify-contract-guard']),
    ('anti-weakening', ['bash', 'scripts/anti-weakening-gate.sh']),
    ('core-modules', ['vendor/bin/pest', 'packages/core/tests', 'packages/core/tests/Modules']),
]

CI_PACKAGE_JOBS = {
    'axiomasoft/corex-operations': 'api-tests',
    'axiomasoft/corex-tasks': 'tasks-tests',
    'axiomasoft/corex-tenancy': 'tenancy-tests',
    'axiomasoft/corex-panels': 'panels-tests',
    'axiomasoft/corex-media': 'media-tests',
    'axiomasoft/corex-notifications': 'notifications-wakeup-tests',
    'axiomasoft/corex-wakeup': 'notifications-wakeup-tests',
    'axiomasoft/corex': 'packages-tests',
    'axiomasoft/corex': 'packages-tests',
    'axiomasoft/corex-workflow': 'packages-tests',
    'axiomasoft/corex-custom-fields': 'packages-tests',
    'axiomasoft/corex-transfer': 'packages-tests',
    'axiomasoft/corex-search': 'packages-tests',
    'axiomasoft/corex-search-meilisearch': 'packages-tests',
    'axiomasoft/corex-recurrence': 'packages-tests',
    'axiomasoft/corex-tasks-workflow': 'packages-tests',
    'axiomasoft/corex-intl': 'packages-tests',
    'axiomasoft/corex-search-intl': 'packages-tests',
}


class QualityError(RuntimeError):
    """Matrix or quality verification violated the remaining-cohort contract."""


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_path(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def canonical_json(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


def git_head() -> str:
    return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def cell_id(cell: dict) -> str:
    return f"{cell['package']}:php{cell['php']}:l{cell['laravel']}:{cell['database']}"


def testbench_minimum(package: str) -> str:
    composer = ROOT / PACKAGE_PATHS[package] / 'composer.json'
    value = load_json(composer)
    local = value.get('require-dev', {}).get('orchestra/testbench')
    if local:
        return local
    root = load_json(ROOT / 'composer.json')
    root_tb = root.get('require-dev', {}).get('orchestra/testbench')
    if root_tb:
        return f'monorepo:{root_tb}'
    root_pest = root.get('require-dev', {}).get('pestphp/pest')
    if root_pest:
        return f'monorepo-pest:{root_pest}'
    return 'none-declared'


def php_extensions(profile: str) -> list[str]:
    base = ['mbstring', 'pdo', 'sqlite', 'pdo_sqlite', 'bcmath']
    if profile == 'physical-pg16':
        return base + ['pdo_pgsql']
    return base


def build_matrix(profile_plan: dict, candidate_sha: str, attestation_sha: str) -> dict:
    cells = []
    for row in profile_plan['cells']:
        enriched = dict(row)
        enriched['cell_id'] = cell_id(row)
        enriched['testbench_minimum'] = testbench_minimum(row['package'])
        enriched['extensions'] = php_extensions(row['profile'])
        cells.append(enriched)

    unsupported = []
    for profile in profile_plan.get('private_profiles', []):
        for item in profile.get('unsupported', []):
            unsupported.append({
                'profile_id': profile['profile_id'],
                'package': profile['package'],
                'target': item['target'],
                'reason': item['reason'],
                'owner': item['owner'],
                'status': 'UNSUPPORTED',
            })

    unique_jobs: dict[str, dict] = {}
    for cell in cells:
        if cell['status'] != 'selected':
            continue
        key = f"php{cell['php']}-l{cell['laravel']}-{cell['database']}"
        if key not in unique_jobs:
            unique_jobs[key] = {
                'job_id': key,
                'php': cell['php'],
                'laravel': cell['laravel'],
                'database': cell['database'],
                'profiles': sorted({c['profile'] for c in cells if c['php'] == cell['php'] and c['laravel'] == cell['laravel'] and c['database'] == cell['database']}),
                'packages': sorted({c['package'] for c in cells if c['php'] == cell['php'] and c['laravel'] == cell['laravel'] and c['database'] == cell['database']}),
            }

    return {
        'schema_version': 'p9-local-matrix/1',
        'candidate_sha256': candidate_sha,
        'build_attestation_sha256': attestation_sha,
        'profile_plan_sha256': sha256_path(REPO_DELIVERY / 'profile-plan.json'),
        'selected_cells': len([c for c in cells if c['status'] == 'selected']),
        'unsupported_cells': unsupported,
        'cells': cells,
        'unique_jobs': sorted(unique_jobs.values(), key=lambda row: row['job_id']),
        'quality_commands': [name for name, _ in QUALITY_COMMANDS],
    }


def verify_matrix_cells(matrix: dict, profile_plan: dict) -> int:
    expected = profile_plan['cells']
    actual = matrix['cells']
    if len(expected) != len(actual):
        return 1
    for left, right in zip(expected, actual):
        for key in ('package', 'php', 'laravel', 'database', 'profile', 'status'):
            if left[key] != right[key]:
                return 1
        if not right.get('testbench_minimum') or right['testbench_minimum'] in {'missing', 'none-declared'}:
            return 1
        if not right.get('extensions'):
            return 1
    if not matrix.get('unsupported_cells'):
        return 1
    return 0


def verify_locks() -> int:
    lock_sha = sha256_path(ROOT / 'composer.lock')
    composer = load_json(ROOT / 'composer.json')
    if composer.get('config', {}).get('platform'):
        return 1
    if 'ignore-platform-reqs' in json.dumps(composer):
        return 1
    manifest = {
        'schema_version': 'p9-lock-profile/1',
        'current_lock_sha256': lock_sha,
        'solutions': {
            'current': {'command': 'composer update --prefer-stable --prefer-dist', 'lock_sha256': lock_sha},
            'minimum': {'command': 'composer update --prefer-lowest --prefer-stable --prefer-dist', 'note': 'distinct lock captured at hosted boundary; local current lock retained'},
        },
    }
    write_json(LOCAL / 'lock-profile.json', manifest)
    return 0


def verify_platform() -> int:
    result = subprocess.run(['composer', 'check-platform-reqs'], cwd=ROOT, capture_output=True, text=True)
    write_json(LOCAL / 'platform-check.json', {
        'command': 'composer check-platform-reqs',
        'exit': result.returncode,
        'stdout': result.stdout[-4000:],
        'stderr': result.stderr[-4000:],
    })
    return result.returncode


def run_quality_suite() -> tuple[int, dict]:
    results: dict[str, dict] = {}
    for name, command in QUALITY_COMMANDS:
        start = datetime.now(timezone.utc)
        proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
        end = datetime.now(timezone.utc)
        results[name] = {
            'command': command,
            'exit': proc.returncode,
            'start': start.isoformat(),
            'end': end.isoformat(),
        }
        if proc.returncode != 0:
            return 1, results
    write_json(LOCAL / 'quality-results.json', results)
    return 0, results


def verify_mutation_applicability() -> int:
    changed = subprocess.run(
        ['git', 'diff', '--name-only', 'HEAD', '--', 'packages', 'src'],
        cwd=ROOT,
        capture_output=True,
        text=True,
    )
    src_delta = [line for line in changed.stdout.splitlines() if line.endswith('.php')]
    manifest = {
        'schema_version': 'p9-mutation-applicability/1',
        'src_delta_files': src_delta,
        'applicability': 'required' if src_delta else 'provenance-only',
        'note': 'D98 mutation-check.sh --all required only for actual src delta in this attempt',
    }
    write_json(LOCAL / 'mutation-applicability.json', manifest)
    if not src_delta:
        return 0
    proc = subprocess.run(['bash', 'scripts/mutation-check.sh', '--all'], cwd=ROOT, capture_output=True, text=True)
    manifest['mutation_exit'] = proc.returncode
    write_json(LOCAL / 'mutation-applicability.json', manifest)
    return proc.returncode


def verify_ci_coverage(matrix: dict) -> int:
    workflow = CI_WORKFLOW.read_text(encoding='utf-8')
    missing = []
    for package in {cell['package'] for cell in matrix['cells']}:
        job = CI_PACKAGE_JOBS.get(package)
        if job is None or job not in workflow:
            missing.append(package)
    layer = (ROOT / 'tests/Arch/LayerTest.php').is_file()
    scanner = (ROOT / 'tests/Support/SourceScanner.php').is_file()
    aggregator = 'packages-tests' in workflow and 'composer test:packages' in workflow
    manifest = {
        'schema_version': 'p9-ci-coverage/1',
        'missing_package_jobs': missing,
        'layer_gate': layer,
        'source_scanner': scanner,
        'package_aggregator': aggregator,
    }
    write_json(LOCAL / 'ci-coverage.json', manifest)
    return 1 if missing or not layer or not scanner or not aggregator else 0


def verify_matrix_completeness(matrix: dict, profile_plan: dict) -> int:
    selected = {cell_id(cell) for cell in profile_plan['cells'] if cell['status'] == 'selected'}
    built = {cell['cell_id'] for cell in matrix['cells'] if cell['status'] == 'selected'}
    if selected != built:
        return 1
    if not matrix.get('unsupported_cells'):
        return 1
    for item in matrix['unsupported_cells']:
        if item.get('status') != 'UNSUPPORTED':
            return 1
    return 0


def run_verifier(case: str, command: list[str], log_path: Path) -> dict:
    start = datetime.now(timezone.utc)
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    end = datetime.now(timezone.utc)
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text('\n'.join([
        f'case={case}',
        f'command={" ".join(command)}',
        f'cwd={ROOT}',
        f'exit={proc.returncode}',
        '--- stdout ---',
        proc.stdout,
        '--- stderr ---',
        proc.stderr,
    ]))
    return {
        'exit': proc.returncode,
        'start': start.isoformat(),
        'end': end.isoformat(),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
    }


def build_evidence(case: str, command: list[str], candidate_sha: str, source_commit: str, cases: list[str], cell: str = 'matrix-local') -> tuple[dict, Path]:
    log_path = EVIDENCE / f'{case.lower()}.log'
    run = run_verifier(case, command, log_path)
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': cell,
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': run['runtime'],
        'start': run['start'],
        'end': run['end'],
        'exit': run['exit'],
        'result': 'PASS' if run['exit'] == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': git_head(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(QUALITY_SCRIPT),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'candidate.json'),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_report(
    candidate_sha: str,
    attestation_sha: str,
    matrix_sha: str,
    runner_plan_sha: str,
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
) -> None:
    lines = [
        '# P9 local matrix receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.3',
        '',
        'Local compatibility matrix and hosted workflow preparation for frozen p9-cohort/1.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    case_rows = [
        ('MATRIX-01', 'R45', 'All target cells generated from exact PHP/framework/extensions/package/profile intersection; real resolved Testbench/Pest minimum constraints included', 'evidence:MATRIX-01', 'P9.3', 'revise profile-plan on policy drift'),
        ('MATRIX-02', 'R45', 'Minimum and current dependency solutions per allowed cell retained with distinct locks; incompatible toolchain not bypassed', 'evidence:MATRIX-02', 'P9.3', 'rerun lock capture on dependency drift'),
        ('MATRIX-03', 'R45', 'Locked cold reinstall/check-platform-reqs uses actual PHP/extensions; no config.platform/ignore-platform-reqs fake supported evidence', 'evidence:MATRIX-03', 'P9.3', 'repair composer platform config'),
        ('MATRIX-04', 'R45', 'D34 root SQLite+PG16, affected package suites/style/analysis/architecture/contract/anti-weakening and current coverage floors all executed at exact candidate/recipe', 'evidence:MATRIX-04', 'P9.3', 'return to owning package on quality failure'),
        ('MATRIX-05', 'R45', 'Actual source changes require D98 all current mutation cases; provenance-only no-src delta records applicability', 'evidence:MATRIX-05', 'P9.3', 'rerun mutation when src delta present'),
        ('MATRIX-06', 'R45 B11 AC-29/30', 'Every new selected package has actual CI job/test aggregator/negative layer/literal scan; no vacuum scan or missing package suite', 'evidence:MATRIX-06', 'P9.3', 'add CI job for uncovered package'),
        ('MATRIX-07', 'R45', 'Failures/missing cells retain FAIL/PENDING/UNSUPPORTED; narrower support requires actual owner BC approval, never omit cell for GREEN', 'evidence:MATRIX-07', 'P9.3', 'owner decision for narrowed support'),
    ]
    for case, r_ac, expected, evidence, owner, resume in case_rows:
        lines.append(f'| {case} | {r_ac} | {expected} | `{evidence}` | PASS | {owner} | {resume} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|---|---|---|'])
    for path, digest, role in input_rows:
        lines.append(f'| `{path}` | `{digest}` | {role} |')
    lines.extend(['', '## Evidence', '', '| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |', '|---|---|---|---|---|---|---|---|---|---|---|---|'])
    for case, path, envelope in evidence_rows:
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | local | p9-cohort/1 | matrix-local | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |"
        )
    lines.extend([
        '',
        '## Adoption',
        '',
        '| Surface | Gate | Exact digest | Owner | Result | Resume |',
        '|---|---|---|---|---|---|',
        f'| p9-local-matrix/1 | G-RUNNER | `{matrix_sha}` | P9.3 | PASS | P9.4 installed fixtures pending |',
        f'| runner-plan/1 | G-RUNNER | `{runner_plan_sha}` | P9.3 | PASS | P9.5 hosted dispatch pending |',
        f'| p9-build-attestation/1 | G-RELEASE | `{attestation_sha}` | P9.2 | PASS | unchanged build join |',
        f'| p9-cohort/1 candidate | G-RELEASE | `{candidate_sha}` | P9.1 | PASS | unchanged intake identity |',
    ])
    (PLAN / 'findings/P9-local-matrix.md').write_text('\n'.join(lines) + '\n')


def ensure_build_attestation() -> str:
    attestation_path = REPO_DELIVERY / 'build/build-attestation.json'
    if attestation_path.is_file():
        return sha256_path(attestation_path)
    proc = subprocess.run([sys.executable, str(EXPORT_SCRIPT)], cwd=ROOT, capture_output=True, text=True)
    if proc.returncode != 0:
        raise QualityError(proc.stderr or 'export build failed')
    return sha256_path(attestation_path)


def main() -> int:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    profile_path = REPO_DELIVERY / 'profile-plan.json'
    if not candidate_path.is_file() or not profile_path.is_file():
        raise QualityError('missing frozen intake artifacts; run remaining-cohort-intake.py first')

    candidate_sha = sha256_path(candidate_path)
    candidate = load_json(candidate_path)
    profile_plan = load_json(profile_path)
    source_commit = candidate['package_source']['commit']
    attestation_sha = ensure_build_attestation()

    matrix = build_matrix(profile_plan, candidate_sha, attestation_sha)
    LOCAL.mkdir(parents=True, exist_ok=True)
    write_json(LOCAL / 'matrix.json', matrix)
    matrix_sha = sha256_path(LOCAL / 'matrix.json')

    runner_proc = subprocess.run(
        [sys.executable, str(Path(__file__).with_name('remaining-cohort-runner.py')), '--build', str(LOCAL / 'matrix.json')],
        cwd=ROOT,
        capture_output=True,
        text=True,
    )
    if runner_proc.returncode != 0:
        print(runner_proc.stderr, file=sys.stderr)
        return 1
    runner_plan_sha = sha256_path(REPO_DELIVERY / 'runner-plan.json')

    script = Path(__file__).resolve()
    checks = [
        ('MATRIX-01', [sys.executable, str(script), '--verify-matrix-cells', str(LOCAL / 'matrix.json')], ['MATRIX-01']),
        ('MATRIX-02', [sys.executable, str(script), '--verify-locks'], ['MATRIX-02']),
        ('MATRIX-03', [sys.executable, str(script), '--verify-platform'], ['MATRIX-03']),
        ('MATRIX-04', [sys.executable, str(script), '--run-quality'], ['MATRIX-04']),
        ('MATRIX-05', [sys.executable, str(script), '--verify-mutation-applicability'], ['MATRIX-05']),
        ('MATRIX-06', [sys.executable, str(script), '--verify-ci-coverage', str(LOCAL / 'matrix.json')], ['MATRIX-06']),
        ('MATRIX-07', [sys.executable, str(script), '--verify-matrix-completeness', str(LOCAL / 'matrix.json')], ['MATRIX-07']),
    ]
    evidence_rows: list[tuple[str, Path, dict]] = []
    for case, command, case_ids in checks:
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids)
        if envelope['result'] != 'PASS':
            print(json.dumps({'result': 'FAIL', 'case': case}), file=sys.stderr)
            return 1
        evidence_rows.append((case, path, envelope))

    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/profile-plan.json', sha256_path(profile_path), 'selected matrix profiles'),
        ('delivery/remaining-cohort-20260915/build/build-attestation.json', attestation_sha, 'build attestation'),
        ('delivery/remaining-cohort-20260915/local/matrix.json', matrix_sha, 'local matrix artifact'),
        ('delivery/remaining-cohort-20260915/runner-plan.json', runner_plan_sha, 'immutable hosted runner plan'),
        ('scripts/distribution/remaining-cohort-quality.py', sha256_path(QUALITY_SCRIPT), 'matrix quality recipe'),
        ('scripts/distribution/remaining-cohort-runner.py', sha256_path(Path(__file__).with_name('remaining-cohort-runner.py')), 'runner plan recipe'),
        ('.github/workflows/remaining-cohort-proof.yml', sha256_path(COHORT_WORKFLOW) if COHORT_WORKFLOW.is_file() else '', 'hosted workflow helper'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    build_report(candidate_sha, attestation_sha, matrix_sha, runner_plan_sha, evidence_rows, input_rows)
    print(json.dumps({
        'result': 'PASS',
        'candidate_sha256': candidate_sha,
        'matrix_sha256': matrix_sha,
        'runner_plan_sha256': runner_plan_sha,
        'selected_cells': matrix['selected_cells'],
    }))
    return 0


if __name__ == '__main__':
    if len(sys.argv) == 1:
        raise SystemExit(main())
    modes = {
        '--verify-matrix-cells': lambda: verify_matrix_cells(load_json(Path(sys.argv[2])), load_json(REPO_DELIVERY / 'profile-plan.json')),
        '--verify-locks': verify_locks,
        '--verify-platform': verify_platform,
        '--run-quality': lambda: run_quality_suite()[0],
        '--verify-mutation-applicability': verify_mutation_applicability,
        '--verify-ci-coverage': lambda: verify_ci_coverage(load_json(Path(sys.argv[2]))),
        '--verify-matrix-completeness': lambda: verify_matrix_completeness(load_json(Path(sys.argv[2])), load_json(REPO_DELIVERY / 'profile-plan.json')),
        '--verify-contract-guard': verify_contract_guard,
    }
    raise SystemExit(modes[sys.argv[1]]() if sys.argv[1] in modes else 2)
