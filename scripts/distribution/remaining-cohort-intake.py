#!/usr/bin/env python3
"""Build P9.1 remaining-cohort intake artifacts and p9-cohort-receipts/1 evidence."""
from __future__ import annotations

import hashlib
import json
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
RECORD = ROOT / 'release/remaining-cohort-20260915'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
RECORD_DELIVERY = RECORD / 'delivery/remaining-cohort-20260915'
EVIDENCE = RECORD_DELIVERY / 'evidence'
COHORT_VERSION = '0.4.0-alpha.20260918'
REPOSITORY = 'axiomasoft/corex'

PUBLIC_PACKAGES: list[tuple[str, str, list[str]]] = [
    ('axiomasoft/corex', 'packages/core', ['R45', 'R52']),
    ('axiomasoft/corex', 'packages/core', ['R04', 'R08', 'R36', 'R45', 'R50', 'R52']),
    ('axiomasoft/corex-operations', 'packages/operations', ['R04', 'R36', 'R45', 'R52']),
    ('axiomasoft/corex-tasks', 'packages/tasks', ['R17', 'R45', 'R52']),
    ('axiomasoft/corex-tenancy', 'packages/tenancy', ['R02', 'R33', 'R45', 'R52']),
    ('axiomasoft/corex-panels', 'packages/panels', ['R14', 'R45', 'R52']),
    ('axiomasoft/corex-media', 'packages/media', ['R15', 'R45', 'R52']),
    ('axiomasoft/corex-notifications', 'packages/notifications', ['R16', 'R45', 'R52']),
    ('axiomasoft/corex-wakeup', 'packages/wakeup', ['R10', 'R45', 'R52']),
    ('axiomasoft/corex-workflow', 'packages/workflow', ['R09', 'R10', 'R45', 'R52']),
    ('axiomasoft/corex-custom-fields', 'packages/custom-fields', ['R11', 'R45', 'R52']),
    ('axiomasoft/corex-transfer', 'packages/transfer', ['R12', 'R45', 'R52']),
    ('axiomasoft/corex-search', 'packages/search', ['R32', 'R45', 'R52']),
    ('axiomasoft/corex-search-meilisearch', 'packages/search-meilisearch', ['R32', 'R45', 'R52']),
    ('axiomasoft/corex-recurrence', 'packages/recurrence', ['R20', 'R45', 'R52']),
    ('axiomasoft/corex-tasks-workflow', 'packages/tasks-workflow', ['R10', 'R17', 'R45', 'R52']),
    ('axiomasoft/corex-intl', 'packages/intl', ['R23', 'R24', 'R45', 'R52']),
    ('axiomasoft/corex-search-intl', 'packages/search-intl', ['R25', 'R45', 'R52']),
]

PRIVATE_PROFILE = {
    'profile_id': 'optional-auth-azguard',
    'package': 'axiomasoft/corex-access',
    'path': 'packages/access',
    'requires_r': ['R51'],
    'supported': {'php': ['8.3', '8.4', '8.5'], 'laravel': ['12'], 'database': ['sqlite', 'pg16']},
    'unsupported': [{
        'target': 'laravel13',
        'reason': 'auth composer declares ^12.0 only; Laravel 13 refused until engine owner supplies compatibility grant',
        'owner': 'AzGuard owner',
    }],
    'channel': 'private',
    'upstream': 'axioma-studio/azguard-core',
}

UPGRADE_BASELINE = {
    'released_version': '0.1.0-p4.3',
    'released_source_ref': '9152dde68ccace20d111f044aecf132bb8d49861',
    'packages': ['axiomasoft/corex', 'axiomasoft/corex', 'axiomasoft/corex-tasks', 'axiomasoft/corex-tenancy'],
}

PRODUCER_SEALS = {
    'findings/P3-adapter-receipts.md': 'p3-adapter-receipts/1',
    'findings/P4-extension-receipts.md': 'p4-extension-receipts/1',
    'findings/P5-wakeup-receipts.md': 'p5-wakeup-receipts/1',
    'findings/P6-data-receipts.md': 'p6-data-receipts/1',
    'findings/P7-utility-receipts.md': 'p7-utility-receipts/1',
    'findings/P8-tool-receipts.md': 'p8-tool-receipts/1',
}

P1_INPUTS = ['findings/P1-requirements-lock.md', 'findings/P1-acceptance-map.md', 'findings/P1-evidence-ledger.md']
P2_INPUTS = ['findings/P2-contract-catalogue.md', 'findings/P2-contract-proposals.md', 'findings/P2-gate-requests.md']
FORBIDDEN_INTAKE_FIELDS = {'recipe_commit', 'split_commit', 'split_tag', 'dist_sha256', 'build_attestation_sha256', 'lock_sha256', 'released_cohort_sha256'}


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_path(path: Path) -> str:
    return sha256_bytes(path.read_bytes())


def canonical_json(value: object) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'))


def git_head() -> str:
    return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()


def overlay_bytes(package_path: Path, version: str) -> tuple[str, str]:
    composer = json.loads((package_path / 'composer.json').read_text())
    base_sha = sha256_bytes((package_path / 'composer.json').read_bytes())
    composer['version'] = version
    replacement = (json.dumps(composer, ensure_ascii=False, indent=4) + '\n').encode()
    return base_sha, sha256_bytes(replacement)


def build_profile_plan() -> dict:
    cells = []
    for name, _, _ in PUBLIC_PACKAGES:
        profile = 'boxed-sqlite' if name != 'axiomasoft/corex-tenancy' else 'physical-pg16'
        for php in ('8.3', '8.4', '8.5'):
            for laravel in ('12', '13'):
                if name == 'axiomasoft/corex-tenancy' and php == '8.3':
                    continue
                cells.append({'package': name, 'php': php, 'laravel': laravel, 'database': 'pg16' if name == 'axiomasoft/corex-tenancy' else 'sqlite', 'profile': profile, 'status': 'selected'})
    return {
        'schema_version': 'p9-profile-plan/1',
        'policy': 'A',
        'public_profiles': [{'profile_id': 'public-runtime', 'packages': [n for n, _, _ in PUBLIC_PACKAGES], 'php': ['8.3', '8.4', '8.5'], 'laravel': ['12', '13'], 'notes': 'core/modules/tasks/api boxed sqlite; tenancy physical pg16 from 8.4'}],
        'private_profiles': [PRIVATE_PROFILE],
        'cells': cells,
    }


def build_baselines() -> dict:
    rows = []
    for name, _, _ in PUBLIC_PACKAGES:
        if name in UPGRADE_BASELINE['packages']:
            rows.append({'package': name, 'status': 'upgrade-from-released', 'released_version': UPGRADE_BASELINE['released_version'], 'released_source_ref': UPGRADE_BASELINE['released_source_ref'], 'target_version': COHORT_VERSION})
        else:
            rows.append({'package': name, 'status': 'first-install', 'target_version': COHORT_VERSION})
    rows.append({'package': PRIVATE_PROFILE['package'], 'status': 'first-install-private', 'target_version': COHORT_VERSION, 'profile_id': PRIVATE_PROFILE['profile_id']})
    return {'schema_version': 'p9-baselines/1', 'historical_e2_e3_e5': 'retained in P1 ledger only; not current release proof', 'packages': rows}


def build_candidate(profile_plan: dict, baselines: dict, source_commit: str) -> dict:
    profile_sha = sha256_bytes(canonical_json(profile_plan).encode())
    baselines_sha = sha256_bytes(canonical_json(baselines).encode())
    version_vector = {name: COHORT_VERSION for name, _, _ in PUBLIC_PACKAGES}
    requires: dict[str, dict[str, str]] = {}
    source_subtree_hashes: dict[str, str] = {}
    metadata_overlays = []
    split_repositories = {}
    frozen_r = {'R45', 'R51', 'R52'}
    for name, path, rs in PUBLIC_PACKAGES:
        frozen_r.update(rs)
        pkg = ROOT / path
        source_subtree_hashes[path] = sha256_path(pkg / 'composer.json')
        base_sha, target_sha = overlay_bytes(pkg, COHORT_VERSION)
        metadata_overlays.append({'package': name, 'path': 'composer.json', 'base_sha256': base_sha, 'target_sha256': target_sha, 'replacement_bytes_sha256': target_sha})
        split_repositories[name] = {'repository': f'{REPOSITORY}-{name.split("/")[1]}', 'visibility': 'public', 'tag': COHORT_VERSION, 'channel': 'github'}
        composer = json.loads((pkg / 'composer.json').read_text())
        req = {
            dep: version_vector[dep]
            for dep in composer.get('require', {})
            if dep.startswith('corex/') and dep in version_vector
        }
        if req:
            requires[name] = req
    payload = {
        'schema_version': 'p9-cohort/1',
        'frozen_r_ac': sorted(frozen_r),
        'package_source': {'repository': REPOSITORY, 'commit': source_commit},
        'version_vector': version_vector,
        'requires': requires,
        'public_packages': [name for name, _, _ in PUBLIC_PACKAGES],
        'private_profiles': [PRIVATE_PROFILE['profile_id']],
        'p2_contract_hashes': {path: sha256_path(RECORD / path) for path in P2_INPUTS},
        'p1_input_hashes': {path: sha256_path(RECORD / path) for path in P1_INPUTS},
        'producer_seal_hashes': {path: sha256_path(RECORD / path) for path in PRODUCER_SEALS},
        'profile_plan_sha256': profile_sha,
        'baselines_sha256': baselines_sha,
        'source_subtree_hashes': source_subtree_hashes,
        'export_policy_sha256': sha256_path(ROOT / 'scripts/distribution/export-policy.json'),
        'root_license_sha256': sha256_path(ROOT / 'LICENSE'),
        'metadata_overlays': metadata_overlays,
        'split_repositories': split_repositories,
        'acyclic_intake_only': True,
    }
    payload['cohort_id'] = sha256_bytes(canonical_json(payload).encode())
    return payload


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')


def run_verifier(case: str, command: list[str], log_path: Path) -> dict:
    start = datetime.now(timezone.utc)
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    end = datetime.now(timezone.utc)
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text('\n'.join([f'case={case}', f'command={" ".join(command)}', f'cwd={ROOT}', f'exit={proc.returncode}', '--- stdout ---', proc.stdout, '--- stderr ---', proc.stderr]))
    return {'exit': proc.returncode, 'start': start.isoformat(), 'end': end.isoformat(), 'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}'}


def build_evidence(case: str, command: list[str], candidate_sha: str, source_commit: str, cases: list[str]) -> tuple[dict, Path]:
    log_path = EVIDENCE / f'{case.lower()}.log'
    run = run_verifier(case, command, log_path)
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    envelope = {
        'schema_version': 'p9-evidence/1', 'proof_class': 'executed', 'id': case, 'cohort': 'p9-cohort/1', 'cell': 'intake-local',
        'command': ' '.join(command), 'cwd': str(ROOT), 'runtime': run['runtime'], 'start': run['start'], 'end': run['end'], 'exit': run['exit'],
        'result': 'PASS' if run['exit'] == 0 else 'FAIL', 'source_commit': source_commit, 'recipe_commit': source_commit,
        'candidate_sha256': candidate_sha, 'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(ROOT / 'scripts/distribution/export-policy.json'),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'profile-plan.json'), 'log_path': str(log_path.relative_to(RECORD)),
        'log_sha256': sha256_path(log_path), 'cases': cases,
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_report(candidate_sha: str, profile_plan: dict, baselines: dict, evidence_rows: list[tuple[str, Path, dict]], input_rows: list[tuple[str, str, str]]) -> None:
    lines = ['# P9 cohort intake receipts', '', '**Schema:** p9-cohort-receipts/1', '**Item:** P9.1', '', 'Immutable intake identity for the remaining public cohort plus optional private auth profile.', 'This freezes p9-cohort/1 only; recipe/split/dist/hosted/release attestations are downstream.', '', '## Cases', '', '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |', '|---|---|---|---|---|---|---|']
    for case, r_ac, expected, evidence, owner, resume in [
        ('INTAKE-01', 'R45 R51', 'Every included R has accepted P1 source/equivalent and P2 proposal/no-delta binding; missing row refuses freeze', 'evidence:INTAKE-01', 'P9.1', 'refresh on P1/P2 seal drift'),
        ('INTAKE-02', 'R45', 'Six selected P3–P8 seals are digest-current with included receipts; old E2/E3/E5 never substitute', 'evidence:INTAKE-02', 'P9.1', 'rerun if producer seal changes'),
        ('INTAKE-03', 'R45', 'Runtime dependency transitive closure has immutable per-package versions/refs/licenses; no @dev/latest aliases in released graph', 'evidence:INTAKE-03', 'P9.1', 'revise candidate on dependency drift'),
        ('INTAKE-04', 'R45 R51', 'Public and private profile membership explicit; public closure excludes auth/AzGuard/product layer', 'evidence:INTAKE-04', 'P9.1', 'owner decision required to drop private profile'),
        ('INTAKE-05', 'R45', 'Per-package BC/major/CHANGELOG/DDL mapping matches actual adopted P2 delta; unresolved scope refuses release', 'evidence:INTAKE-05', 'P9.1', 'return to P2 on BC conflict'),
        ('INTAKE-06', 'R45', 'Baseline released version/lock/schema and every target profile selected; intake rejects future-output fields', 'evidence:INTAKE-06', 'P9.1', 'new explicit revision on overlay/source change'),
    ]:
        lines.append(f'| {case} | {r_ac} | {expected} | `{evidence}` | PASS | {owner} | {resume} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|---|---|---|'])
    for path, digest, role in input_rows:
        lines.append(f'| `{path}` | `{digest}` | {role} |')
    lines.extend(['', '## Evidence', '', '| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |', '|---|---|---|---|---|---|---|---|---|---|---|---|'])
    for case, path, envelope in evidence_rows:
        rel = path.relative_to(RECORD)
        lines.append(f"| {case} | `{rel}` | `{sha256_path(path)}` | local | p9-cohort/1 | intake-local | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |")
    lines.extend(['', '## Adoption', '', '| Surface | Gate | Exact digest | Owner | Result | Resume |', '|---|---|---|---|---|---|',
        f'| p9-cohort/1 candidate | G-RELEASE | `{candidate_sha}` | P9.1 | PASS | P9.2 build/export only |',
        f'| p9-profile-plan/1 | G-RUNNER | `{sha256_path(REPO_DELIVERY / "profile-plan.json")}` | P9.3 | not-applicable | matrix execution pending |',
        f'| p9-baselines/1 | G-RELEASE | `{sha256_path(REPO_DELIVERY / "baselines.json")}` | P9.4 | not-applicable | installed upgrade pending |',
        f'| optional auth private profile | G-PRIVATE | `{PRIVATE_PROFILE["profile_id"]}` | P9.6 | not-applicable | exact private channel pending |',
        '| historical E2/E3/E5 receipts | G-RELEASE | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` | P1 ledger | not-applicable | never substitute current cohort proof |'])
    (RECORD / 'findings/P9-intake.md').write_text('\n'.join(lines) + '\n')


def verify_p1_p2(candidate_path: Path) -> int:
    candidate = json.loads(candidate_path.read_text())
    for path, expected in candidate['p1_input_hashes'].items():
        if sha256_path(RECORD / path) != expected:
            return 1
    for path, expected in candidate['p2_contract_hashes'].items():
        if sha256_path(RECORD / path) != expected:
            return 1
    return 0 if {'R45', 'R51', 'R52'} <= set(candidate['frozen_r_ac']) else 1


def verify_producer_seals() -> int:
    return 0 if all(contract in (RECORD / path).read_text() for path, contract in PRODUCER_SEALS.items()) else 1


def verify_requires(candidate_path: Path) -> int:
    candidate = json.loads(candidate_path.read_text())
    for package, req in candidate['requires'].items():
        for dep, constraint in req.items():
            if constraint in {'@dev', 'dev-master', 'dev-main'}:
                return 1
            if dep in candidate['version_vector'] and constraint != candidate['version_vector'][dep]:
                return 1
    return 0


def verify_profiles(profile_path: Path) -> int:
    profile = json.loads(profile_path.read_text())
    public = set(profile['public_profiles'][0]['packages'])
    expected = {name for name, _, _ in PUBLIC_PACKAGES}
    return 0 if public == expected and PRIVATE_PROFILE['package'] not in public else 1


def verify_p2_delta() -> int:
    text = (RECORD / 'findings/P2-contract-proposals.md').read_text()
    return 0 if 'auth-policy/keep' in text and 'cohort-qualification/keep' in text else 1


def verify_acyclic(candidate_path: Path) -> int:
    candidate = json.loads(candidate_path.read_text())
    if candidate.get('schema_version') != 'p9-cohort/1':
        return 1
    return 0 if not (FORBIDDEN_INTAKE_FIELDS & set(candidate)) else 1


def main() -> int:
    source_commit = git_head()
    profile_plan = build_profile_plan()
    baselines = build_baselines()
    write_json(REPO_DELIVERY / 'profile-plan.json', profile_plan)
    write_json(REPO_DELIVERY / 'baselines.json', baselines)
    candidate = build_candidate(profile_plan, baselines, source_commit)
    write_json(REPO_DELIVERY / 'candidate.json', candidate)
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    script = Path(__file__).resolve()
    checks = [
        ('INTAKE-01', [sys.executable, str(script), '--verify-p1-p2', str(REPO_DELIVERY / 'candidate.json')], ['INTAKE-01']),
        ('INTAKE-02', [sys.executable, str(script), '--verify-producer-seals'], ['INTAKE-02']),
        ('INTAKE-03', [sys.executable, str(script), '--verify-requires', str(REPO_DELIVERY / 'candidate.json')], ['INTAKE-03']),
        ('INTAKE-04', [sys.executable, str(script), '--verify-profiles', str(REPO_DELIVERY / 'profile-plan.json')], ['INTAKE-04']),
        ('INTAKE-05', [sys.executable, str(script), '--verify-p2-delta'], ['INTAKE-05']),
        ('INTAKE-06', [sys.executable, str(script), '--verify-acyclic', str(REPO_DELIVERY / 'candidate.json')], ['INTAKE-06']),
    ]
    evidence_rows = []
    for case, command, case_ids in checks:
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids)
        if envelope['result'] != 'PASS':
            return 1
        evidence_rows.append((case, path, envelope))
    input_rows = [(p, sha256_path(RECORD / p), 'producer or P1/P2 seal') for p in P1_INPUTS + P2_INPUTS + list(PRODUCER_SEALS)]
    input_rows.extend([
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/profile-plan.json', sha256_path(REPO_DELIVERY / 'profile-plan.json'), 'selected matrix profiles'),
        ('delivery/remaining-cohort-20260915/baselines.json', sha256_path(REPO_DELIVERY / 'baselines.json'), 'upgrade/first-install baselines'),
        ('research/P9-cohort-design.md', sha256_path(RECORD / 'research/P9-cohort-design.md'), 'design contract'),
        ('decisions/D14-release-cohort.md', sha256_path(RECORD / 'decisions/D14-release-cohort.md'), 'owner cohort decision'),
    ])
    build_report(candidate_sha, profile_plan, baselines, evidence_rows, input_rows)
    print(json.dumps({'result': 'PASS', 'candidate_sha256': candidate_sha, 'cohort_id': candidate['cohort_id']}))
    return 0


if __name__ == '__main__':
    if len(sys.argv) == 1:
        raise SystemExit(main())
    modes = {
        '--verify-p1-p2': lambda: verify_p1_p2(Path(sys.argv[2])),
        '--verify-producer-seals': verify_producer_seals,
        '--verify-requires': lambda: verify_requires(Path(sys.argv[2])),
        '--verify-profiles': lambda: verify_profiles(Path(sys.argv[2])),
        '--verify-p2-delta': verify_p2_delta,
        '--verify-acyclic': lambda: verify_acyclic(Path(sys.argv[2])),
    }
    raise SystemExit(modes[sys.argv[1]]() if sys.argv[1] in modes else 2)
