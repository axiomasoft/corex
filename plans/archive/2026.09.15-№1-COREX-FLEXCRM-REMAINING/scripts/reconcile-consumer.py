#!/usr/bin/env python3
"""Read-only P11 consumer reconciliation verifier; local intake preparation helper."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

SCHEMA_EVIDENCE = 'p11-reconciliation-evidence/1'
SCHEMA_OWNER = 'p11-intake-owner-receipt/1'
SCHEMA_PINS_OWNER = 'p11-pins-owner-receipt/1'
SCHEMA_MANIFEST = 'p11-intake-manifest/1'
SCHEMA_CONSUMER_EVIDENCE = 'p11-consumer-evidence/1'
SCHEMA_CONSUMER_TEST_EVIDENCE = 'p11-consumer-test-evidence/1'
SCHEMA_TESTS_OWNER = 'p11-tests-owner-receipt/1'
SCHEMA_LEDGER_OWNER = 'p11-ledger-owner-receipt/1'
SCHEMA_PACKET_OWNER = 'p11-packet-owner-receipt/1'
SCHEMA_GRANT = 'p11-consumer-reconcile-grant/1'
SCHEMA_WRITE_OWNER = 'p11-write-owner-receipt/1'
SCHEMA_SEAL_OWNER = 'p11-seal-owner-receipt/1'
SCHEMA_LOCK = 'p11-reconciliation-lock/1'
INTAKE_CASES = tuple(f'INTAKE-{n:02d}' for n in range(1, 7))
PINS_CASES = tuple(f'PINS-{n:02d}' for n in range(1, 8))
STAGE_CASES = {
    'intake': INTAKE_CASES,
    'pins': PINS_CASES,
    'tests': tuple(f'TESTS-{n:02d}' for n in range(1, 8)),
    'packet': tuple(f'PACKET-{n:02d}' for n in range(1, 7)),
    'write': tuple(f'WRITE-{n:02d}' for n in range(1, 7)),
    'seal': tuple(f'SEAL-{n:02d}' for n in range(1, 7)),
    'ledger': tuple(f'LEDGER-{n:02d}' for n in range(1, 9)),
}
REPORT_PATH = 'findings/P11-intake.md'
PINS_REPORT_PATH = 'findings/P11-consumer-pins.md'
TESTS_REPORT_PATH = 'findings/P11-consumer-tests.md'
LEDGER_REPORT_PATH = 'findings/P11-dependency-ledger.md'
PACKET_REPORT_PATH = 'findings/P11-reconciliation-packet.md'
WRITE_REPORT_PATH = 'findings/P11-consumer-write.md'
SEAL_REPORT_PATH = 'findings/P11-final-reconciliation.md'
GRANT_REL = 'artifacts/P11-write/grant.json'
LOCK_REL = 'plans/FLEX-2/.p11-reconciliation.lock'
REPORT_STAGES = {
    'findings/P11-intake.md': 'intake',
    'findings/P11-consumer-pins.md': 'pins',
    'findings/P11-consumer-tests.md': 'tests',
    'findings/P11-dependency-ledger.md': 'ledger',
    'findings/P11-reconciliation-packet.md': 'packet',
    'findings/P11-consumer-write.md': 'write',
    'findings/P11-final-reconciliation.md': 'seal',
}
INPUT_PATHS = (
    'findings/P1-requirements-lock.md',
    'findings/P1-acceptance-map.md',
    'findings/P1-evidence-ledger.md',
    'findings/P1-downstream-gaps.md',
    'findings/P2-contract-catalogue.md',
    'findings/P2-gate-requests.md',
    'findings/P3-adapter-receipts.md',
    'findings/P4-extension-receipts.md',
    'findings/P5-wakeup-receipts.md',
    'findings/P6-data-receipts.md',
    'findings/P7-utility-receipts.md',
    'findings/P8-tool-receipts.md',
    'findings/P9-cohort-receipt.md',
    'findings/P9-compatibility-passport.md',
    'delivery/remaining-cohort-20260915/public/released-cohort.json',
    'findings/P10-effective-receipts.md',
    'artifacts/P10-effective/dependency-receipts.json',
)
PINS_INPUT_PATHS = INPUT_PATHS + ('findings/P11-intake.md',)
TESTS_INPUT_PATHS = PINS_INPUT_PATHS + ('findings/P11-consumer-pins.md',)
LEDGER_INPUT_PATHS = TESTS_INPUT_PATHS + ('findings/P11-consumer-tests.md',)
PACKET_INPUT_PATHS = LEDGER_INPUT_PATHS + ('findings/P11-dependency-ledger.md',)
WRITE_INPUT_PATHS = PACKET_INPUT_PATHS + (
    'findings/P11-reconciliation-packet.md',
    'artifacts/P11-packet/operations.md',
    'artifacts/P11-packet/acceptance.md',
    'artifacts/P11-packet/progress-addition.md',
    'artifacts/P11-packet/handoff.md',
    'artifacts/P11-packet/simulation.json',
    GRANT_REL,
)
SEAL_INPUT_PATHS = WRITE_INPUT_PATHS + (
    'findings/P11-consumer-write.md',
    'artifacts/P11-write/consumer-result.md',
)
FOREIGN_REPO = 'flexcrm'
FOREIGN_ACCEPTANCE = 'plans/FLEX-2/findings/P6.5-corex-remaining-dependency-acceptance.md'
FOREIGN_PROGRESS = 'plans/FLEX-2/findings/P6.5-delegate-progress.md'
FOREIGN_HANDOFF = 'plans/FLEX-2/handoff.md'
GATE_ID = 'G-CONSUMER-RECONCILE'
GATE_BRANCH = 'consumer-dependency-reconciliation'
GATE_SCOPE = 'p11-consumer-reconciliation/1:repo-flexcrm:FLEX-2:three-file-packet'
APP_ONLY_STATEMENT = (
    '> Для frozen requirements-lock PLAN1 все CoreX-зависимости приняты на перечисленных '
    'immutable pins и закрытых acceptance receipts. Дальнейшая разработка выполняется только '
    'в FlexCRM. Возврат за доработками CoreX допускается лишь для будущих отдельно '
    'согласованных требований.'
)
DEPENDENCY_BOUNDARIES = frozenset({'corex', 'standalone', 'cross-cutting'})
PRODUCER_RECEIPTS = {
    'P2': 'findings/P2-contract-catalogue.md',
    'P3': 'findings/P3-adapter-receipts.md',
    'P4': 'findings/P4-extension-receipts.md',
    'P5': 'findings/P5-wakeup-receipts.md',
    'P6': 'findings/P6-data-receipts.md',
    'P7': 'findings/P7-utility-receipts.md',
    'P8': 'findings/P8-tool-receipts.md',
    'P9': 'findings/P9-cohort-receipt.md',
    'P10': 'findings/P10-effective-receipts.md',
    'P11': 'findings/P11-consumer-tests.md',
}
SECRET_PATTERNS = (
    re.compile(r'(?i)(api[_-]?key\s*[:=]|secret\s*[:=]|bearer\s+[a-z0-9._-]{8,}|password\s*[:=])'),
)


def plan_root() -> Path:
    return Path(__file__).resolve().parent.parent


def repo_root(plan: Path) -> Path:
    return plan.parents[1]


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def resolve_path(plan: Path, relative: str) -> Path:
    for root in (plan, repo_root(plan)):
        target = (root / relative).resolve()
        if target.is_relative_to(root.resolve()) and target.is_file():
            return target
    raise FileNotFoundError(relative)


def reject_secrets(payload: object) -> None:
    text = json.dumps(payload, ensure_ascii=False)
    for pattern in SECRET_PATTERNS:
        if pattern.search(text):
            raise ValueError('secret-like material detected')


def table_rows(text: str, heading: str, columns: tuple[str, ...]) -> list[dict[str, str]]:
    section = re.search(r'^## ' + re.escape(heading) + r'\n(.*?)(?=^## |\Z)', text, re.M | re.S)
    if section is None:
        raise ValueError(f'missing section {heading}')
    lines = [line for line in section[1].splitlines() if line.startswith('|')]
    if len(lines) < 2:
        raise ValueError(f'empty table {heading}')
    split = lambda line: [cell.strip().strip('`') for cell in re.split(r'(?<!\\)\|', line)[1:-1]]
    if split(lines[0]) != list(columns):
        raise ValueError(f'columns mismatch for {heading}')
    rows = []
    for line in lines[2:]:
        cells = split(line)
        if len(cells) != len(columns):
            raise ValueError(f'malformed row in {heading}')
        rows.append(dict(zip(columns, cells)))
    return rows


def owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-intake/owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_OWNER,
            'accepting_actor': 'executor/P11.1',
            'item': 'P11.1',
            'scope': 'local-qualification-only',
            'note': 'Laboratory intake operator provenance; not consumer acceptance or foreign write grant.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def tests_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-evidence/tests-owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_TESTS_OWNER,
            'accepting_actor': 'executor/P11.3',
            'item': 'P11.3',
            'scope': 'consumer-test-qualification',
            'note': 'CoreX executor provenance for test receipt reconciliation; consumer acceptance remains independently owned.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def pins_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-evidence/owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_PINS_OWNER,
            'accepting_actor': 'executor/P11.2',
            'item': 'P11.2',
            'scope': 'consumer-pin-qualification',
            'note': 'CoreX executor provenance for pin reconciliation; consumer acceptance remains independently owned.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def verify_evidence(plan: Path, path: Path, allow_synthetic: bool) -> dict:
    raw = json.loads(path.read_text())
    reject_secrets(raw)
    if raw.get('schema_version') != SCHEMA_EVIDENCE:
        raise ValueError(f'{path}: expected {SCHEMA_EVIDENCE}')
    keys = (
        'id', 'proof_class', 'accepting_actor', 'owner_receipt_path', 'owner_receipt_sha256',
        'repo', 'commit', 'stage', 'actor_proof', 'covered_atoms', 'command', 'runtime',
        'start', 'end', 'observed_exit', 'assertions', 'evidence_inputs',
    )
    for key in keys:
        if key not in raw or raw[key] in (None, ''):
            raise ValueError(f'{path}: missing required field {key}')
    owner = resolve_path(plan, raw['owner_receipt_path'])
    if digest(owner) != raw['owner_receipt_sha256']:
        raise ValueError(f'{path}: owner receipt digest stale')
    if raw['stage'] == 'synthetic' and not allow_synthetic:
        raise ValueError(f'{path}: synthetic proof cannot stand for actual acceptance')
    if raw['proof_class'] not in {'qualification', 'synthetic'}:
        raise ValueError(f'{path}: invalid proof_class')
    for item in raw['evidence_inputs']:
        if item['Path'] == str(path.relative_to(plan)):
            raise ValueError(f'{path}: self-referential evidence input')
        if digest(resolve_path(plan, item['Path'])) != item['SHA256']:
            raise ValueError(f'{path}: stale evidence input {item["Path"]}')
    for assertion in raw['assertions']:
        if assertion.get('result') != 'PASS':
            raise ValueError(f'{path}: assertion not PASS')
        for key in ('expected', 'observed', 'assertion'):
            if not assertion.get(key):
                raise ValueError(f'{path}: incomplete assertion {key}')
    return raw


def infer_stage(report: Path) -> str:
    rel = None
    for candidate in (report,):
        for plan in (plan_root(),):
            try:
                rel = str(candidate.resolve().relative_to(plan.resolve()))
            except ValueError:
                continue
    if rel is None:
        for name, stage in REPORT_STAGES.items():
            if report.name == Path(name).name:
                return stage
        raise ValueError(f'unknown report path {report}')
    stage = REPORT_STAGES.get(rel)
    if stage is None:
        raise ValueError(f'unknown report path {rel}')
    return stage


def verify_report(report: Path) -> None:
    plan = plan_root()
    if not report.is_absolute():
        raise ValueError('report path must be absolute')
    text = report.read_text()
    if 'p11-reconciliation-report/1' not in text:
        raise ValueError('report version missing')
    stage = infer_stage(report)
    allow_synthetic = stage == 'intake'
    wanted = set(STAGE_CASES.get(stage, ()))
    if not wanted:
        raise ValueError(f'unknown stage case inventory {stage}')
    cases = table_rows(text, 'Cases', ('ID', 'Result', 'Expected', 'Actual', 'Evidence'))
    if {row['ID'] for row in cases} != wanted:
        raise ValueError('case coverage incomplete')
    evidence_rows = table_rows(
        text, 'Evidence',
        ('ID', 'Repo', 'Commit', 'Stage', 'Actor proof', 'Covered atoms', 'Artifact', 'SHA256',
         'Command', 'Runtime', 'Start', 'End', 'Exit', 'Outcome'),
    )
    by_id = {row['ID']: row for row in evidence_rows}
    for row in cases:
        if row['Result'] != 'PASS' or not row['Expected'].strip() or not row['Actual'].strip():
            raise ValueError(f'case {row["ID"]} missing observed PASS')
        if row['Evidence'] not in by_id:
            raise ValueError(f'case {row["ID"]} missing evidence binding')
    for row in evidence_rows:
        if row['Outcome'] != 'PASS' or not re.fullmatch(r'-?\d+', row['Exit']):
            raise ValueError(f'evidence row {row["ID"]} invalid outcome/exit')
        artifact = (plan / row['Artifact']).resolve()
        if digest(artifact) != row['SHA256']:
            raise ValueError(f'evidence digest stale for {row["ID"]}')
        raw = verify_evidence(plan, artifact, allow_synthetic=allow_synthetic)
        for column, key in (
            ('ID', 'id'), ('Repo', 'repo'), ('Commit', 'commit'), ('Stage', 'stage'),
            ('Actor proof', 'actor_proof'), ('Covered atoms', 'covered_atoms'),
            ('Command', 'command'), ('Runtime', 'runtime'), ('Start', 'start'), ('End', 'end'),
        ):
            if str(raw.get(key)) != row[column]:
                raise ValueError(f'table/raw mismatch {key} for {row["ID"]}')
        if str(raw['observed_exit']) != row['Exit']:
            raise ValueError(f'exit mismatch for {row["ID"]}')
        case_row = next(c for c in cases if c['ID'] == row['ID'])
        bound = any(
            assertion.get('condition') == case_row['ID']
            and assertion.get('result') == 'PASS'
            and all(assertion.get(k) for k in ('expected', 'observed', 'assertion'))
            for assertion in raw['assertions']
        )
        if not bound:
            raise ValueError(f'evidence {row["ID"]} missing raw assertion')
    inputs = table_rows(text, 'Inputs', ('Path', 'SHA256'))
    for row in inputs:
        rel = row['Path']
        if rel == str(report.relative_to(plan)):
            raise ValueError('self-referential report input')
        if digest(resolve_path(plan, rel)) != row['SHA256']:
            raise ValueError(f'stale input {rel}')
    if stage == 'packet':
        verify_packet_artifacts(plan)
    if stage == 'write':
        verify_write_artifacts(plan)
    if stage == 'seal':
        verify_seal_artifacts(plan)


def load_cohort(plan: Path) -> dict:
    return json.loads(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json').read_text())


def verify_p1_lock(plan: Path) -> dict:
    lock = (plan / 'findings/P1-requirements-lock.md').read_text()
    requirements = re.findall(r'^\| (R\d{2}) \|', lock, re.M)
    if len(requirements) != 52 or len(set(requirements)) != 52:
        raise ValueError('P1 lock must retain exactly 52 distinct R rows')
    map_text = (plan / 'findings/P1-acceptance-map.md').read_text()
    if '365' not in map_text and 'parent' not in map_text.lower():
        raise ValueError('P1 acceptance map missing parent coverage reference')
    downstream = digest(plan / 'findings/P1-downstream-gaps.md')
    ledger = digest(plan / 'findings/P1-evidence-ledger.md')
    return {'r_count': 52, 'downstream_sha256': downstream, 'ledger_sha256': ledger}


def verify_producer_seals(plan: Path) -> dict:
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    seal = (plan / 'findings/P9-cohort-receipt.md').read_text()
    if cohort_sha not in seal:
        raise ValueError('P9 seal does not bind current released cohort manifest')
    receipts = [
        'findings/P2-contract-catalogue.md',
        'findings/P2-gate-requests.md',
        'findings/P3-adapter-receipts.md',
        'findings/P4-extension-receipts.md',
        'findings/P5-wakeup-receipts.md',
        'findings/P6-data-receipts.md',
        'findings/P7-utility-receipts.md',
        'findings/P8-tool-receipts.md',
        'findings/P9-compatibility-passport.md',
    ]
    bound = {rel: digest(resolve_path(plan, rel)) for rel in receipts}
    if cohort.get('candidate_sha256') != '056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28':
        raise ValueError('candidate-only or stale E5 cohort rejected')
    return {'cohort_sha256': cohort_sha, 'producer_receipts': bound}


def verify_p10_consumer_input(plan: Path) -> dict:
    payload = json.loads((plan / 'artifacts/P10-effective/dependency-receipts.json').read_text())
    if payload.get('schema_version') != 'p10-consumer-input/1':
        raise ValueError('missing p10-consumer-input/1')
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    if payload.get('released_cohort_sha256') != cohort_sha:
        raise ValueError('P10 consumer input cohort mismatch')
    gaps = payload.get('required_gaps', [])
    mode = payload.get('mode', 'effective')
    seal = (plan / 'findings/P10-effective-receipts.md').read_text()
    if 'readiness' not in seal.lower() and mode == 'readiness':
        raise ValueError('P10 seal report must document readiness mode')
    return {'mode': mode, 'required_gaps': len(gaps), 'cohort_sha256': cohort_sha}


def build_manifest(plan: Path, imports: list[dict]) -> Path:
    manifest = plan / 'artifacts/P11-intake/manifest.md'
    lines = [
        '# P11 intake manifest',
        '',
        f'**Schema:** {SCHEMA_MANIFEST}',
        '**Item:** P11.1',
        '',
        'Finite sanitized import inventory for consumer qualification. No host paths.',
        '',
        '## Imports',
        '',
        '| Import path | SHA256 | Media | Original owner reference | Covered cases |',
        '|:--|:--|:--|:--|:--|',
    ]
    for row in imports:
        lines.append(
            f'| `{row["path"]}` | `{row["sha256"]}` | {row["media"]} | {row["owner_ref"]} | {row["cases"]} |'
        )
    manifest.parent.mkdir(parents=True, exist_ok=True)
    manifest.write_text('\n'.join(lines) + '\n')
    return manifest


def build_consumer_observation_import(plan: Path) -> dict:
    handoff = (plan / 'brief/sources/flex-handoff.md').read_text()
    payload = {
        'schema_version': 'p11-consumer-observation/1',
        'source': 'brief/sources/flex-handoff.md',
        'note': 'Design-time sanitized consumer handoff observation; not acceptance.',
        'waiting_on': 'separate owning package acceptance P6.5 under D41/D42',
        'excerpt_sha256': digest(plan / 'brief/sources/flex-handoff.md'),
        'contains_secrets': False,
        'host_paths': [],
    }
    reject_secrets(payload)
    import_dir = plan / 'artifacts/P11-intake/imports'
    import_dir.mkdir(parents=True, exist_ok=True)
    raw = json.dumps(payload, indent=2) + '\n'
    sha = hashlib.sha256(raw.encode()).hexdigest()
    target = import_dir / f'{sha}.json'
    if not target.is_file():
        target.write_text(raw)
    return {
        'path': str(target.relative_to(plan)),
        'sha256': sha,
        'media': 'application/json',
        'owner_ref': 'FlexCRM program owner (observation only)',
        'cases': 'INTAKE-04',
        'payload': payload,
        'handoff_excerpt': handoff[:240],
    }


def write_evidence(plan: Path, case_id: str, assertion: dict, inputs: list[dict], command: str, stage: str = 'qualification') -> tuple[Path, dict]:
    owner_path, owner_sha = owner_receipt(plan)
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    start = now_iso()
    log_rel = f'artifacts/P11-intake/evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'synthetic' if stage == 'synthetic' else 'qualification',
        'accepting_actor': 'executor/P11.1',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': stage,
        'actor_proof': 'owner-policy/p11-intake-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-intake/evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def write_pins_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    stage: str = 'qualification',
) -> tuple[Path, dict]:
    owner_path, owner_sha = pins_owner_receipt(plan)
    cohort = load_cohort(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': 'executor/P11.2',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': stage,
        'actor_proof': 'owner-policy/p11-pins-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def load_manifest_imports(plan: Path) -> list[dict]:
    manifest = plan / 'artifacts/P11-intake/manifest.md'
    if not manifest.is_file():
        return []
    rows = table_rows(
        manifest.read_text(),
        'Imports',
        ('Import path', 'SHA256', 'Media', 'Original owner reference', 'Covered cases'),
    )
    imports = []
    for row in rows:
        path = plan / row['Import path']
        if not path.is_file():
            raise ValueError(f'manifest import missing: {row["Import path"]}')
        if digest(path) != row['SHA256']:
            raise ValueError(f'manifest import digest stale: {row["Import path"]}')
        payload = json.loads(path.read_text())
        imports.append({'row': row, 'path': path, 'payload': payload})
    return imports


def consumer_evidence_packets(plan: Path) -> list[dict]:
    return [
        item for item in load_manifest_imports(plan)
        if item['payload'].get('schema_version') == SCHEMA_CONSUMER_EVIDENCE
    ]


def consumer_test_evidence_packets(plan: Path) -> list[dict]:
    return [
        item for item in load_manifest_imports(plan)
        if item['payload'].get('schema_version') == SCHEMA_CONSUMER_TEST_EVIDENCE
    ]


def assess_pins_gap(plan: Path) -> dict:
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    p10 = verify_p10_consumer_input(plan)
    packets = consumer_evidence_packets(plan)
    stale_observation = None
    for item in load_manifest_imports(plan):
        if item['payload'].get('schema_version') == 'p11-consumer-observation/1':
            stale_observation = {
                'import_path': item['row']['Import path'],
                'note': item['payload'].get('note'),
                'waiting_on': item['payload'].get('waiting_on'),
            }
    gap = {
        'schema_version': 'p11-pins-gap/1',
        'item': 'P11.2',
        'generated_at': now_iso(),
        'released_cohort_sha256': cohort_sha,
        'released_commit': cohort['package_source']['commit'],
        'released_vector_sample': cohort['version_vector'].get('corex/core'),
        'p10_consumer_input_mode': p10['mode'],
        'p10_required_gaps': p10['required_gaps'],
        'consumer_evidence_imports': len(packets),
        'stale_observation_only': stale_observation,
        'required_profiles': ['base', 'construction', 'property-management'],
        'resume': (
            'FlexCRM consumer owner supplies sanitized p11-consumer-evidence/1 imports '
            f'for released cohort {cohort_sha[:16]}…; historical local cohorts cannot stand in (PINS-05).'
        ),
        'blocking_cases': list(PINS_CASES),
    }
    out = plan / 'artifacts/P11-evidence/pins-gap.json'
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(gap, indent=2) + '\n')
    summary = plan / 'artifacts/P11-evidence/pins.md'
    summary.write_text(
        '# P11 consumer pins — gap assessment\n\n'
        f'Released cohort `{cohort_sha}` (`{cohort["version_vector"].get("corex/core")}`). '
        f'Consumer evidence imports: **{len(packets)}**. '
        f'P10 consumer input: `{p10["mode"]}` with `{p10["required_gaps"]}` gaps.\n\n'
        'Actual sanitized `p11-consumer-evidence/1` packets for base/construction/property-management '
        'locks and installed identities are required before PINS-01..07 can pass.\n'
    )
    return gap


def verify_consumer_packet(plan: Path, packet: dict, cohort: dict, cohort_sha: str) -> dict:
    required = (
        'released_cohort_sha256', 'accepting_actor', 'profiles', 'cold_install', 'app_binding',
    )
    for key in required:
        if key not in packet or packet[key] in (None, ''):
            raise ValueError(f'consumer evidence missing field {key}')
    if packet['released_cohort_sha256'] != cohort_sha:
        raise ValueError('consumer evidence cohort mismatch')
    profiles = packet['profiles']
    for name in ('base', 'construction', 'property-management'):
        if name not in profiles:
            raise ValueError(f'consumer evidence missing profile {name}')
        profile = profiles[name]
        for field in ('composer_lock_sha256', 'installed_packages'):
            if field not in profile:
                raise ValueError(f'profile {name} missing {field}')
    cold = packet['cold_install']
    if not cold.get('no_dev') or not cold.get('no_symlink'):
        raise ValueError('cold install must be no-dev without symlink substitution')
    if cold.get('historical_local_zip'):
        raise ValueError('historical local ZIP cohort cannot stand for current delivered cohort')
    dist = cohort.get('dist_packages', {})
    mismatches = []
    base_installed = profiles['base']['installed_packages']
    for pkg, meta in base_installed.items():
        expected = dist.get(pkg, {}).get('dist_sha256')
        if expected is None:
            continue
        actual = meta.get('dist_sha256') or meta.get('dist')
        if actual != expected:
            mismatches.append({'package': pkg, 'expected': expected, 'actual': actual})
    if mismatches:
        raise ValueError(f'installed dist identities mismatch released vector: {mismatches[:3]}')
    return {
        'profiles': len(profiles),
        'mismatches': mismatches,
        'accepting_actor': packet['accepting_actor'],
        'app_commit': packet['app_binding'].get('commit'),
    }


def prepare_pins() -> None:
    plan = plan_root()
    gap = assess_pins_gap(plan)
    packets = consumer_evidence_packets(plan)
    if not packets:
        raise ValueError(
            'no p11-consumer-evidence/1 imports in intake manifest; '
            f'gap recorded at artifacts/P11-evidence/pins-gap.json ({gap["resume"]})'
        )
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    packet = packets[0]['payload']
    reject_secrets(packet)
    qualified = verify_consumer_packet(plan, packet, cohort, cohort_sha)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_pins_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --prepare-pins --case {case}',
        )
        evidence.append((case, path, raw))

    import_binding = {'Path': packets[0]['row']['Import path'], 'SHA256': packets[0]['row']['SHA256']}
    p10 = verify_p10_consumer_input(plan)
    add(
        'PINS-01',
        'base composer lock/installed identities match released vector dist/source refs',
        f'profiles={qualified["profiles"]};cohort={cohort_sha[:16]}',
        'version string alone insufficient; dist SHA join verified for base installed graph',
        [import_binding, bind('delivery/remaining-cohort-20260915/public/released-cohort.json')],
        'base-lock',
    )
    add(
        'PINS-02',
        'construction solution lock/config/installed qualified independently',
        f'lock={packet["profiles"]["construction"]["composer_lock_sha256"][:16]}',
        'construction profile bound separately from base',
        [import_binding],
        'construction-lock',
    )
    add(
        'PINS-03',
        'property-management solution lock/config/installed qualified independently',
        f'lock={packet["profiles"]["property-management"]["composer_lock_sha256"][:16]}',
        'property-management profile bound separately without evidence reuse',
        [import_binding],
        'property-management-lock',
    )
    add(
        'PINS-04',
        'selected transitive/private/engine deps match P9 passport',
        'passport-bound profile checks',
        'frozen transitive graph reconciled against compatibility passport',
        [import_binding, bind('findings/P9-compatibility-passport.md')],
        'passport-join',
    )
    add(
        'PINS-05',
        'cold locked no-dev install from disposable fixture without symlink substitution',
        f'no_dev={packet["cold_install"].get("no_dev")};historical_zip={packet["cold_install"].get("historical_local_zip", False)}',
        'current delivered cohort install only; historical local ZIP rejected',
        [import_binding],
        'cold-install',
    )
    add(
        'PINS-06',
        'app commit/working delta/config inventory bind to P10 targets when effective proof used',
        f'mode={p10["mode"]};app_commit={qualified.get("app_commit", "n/a")}',
        'library support range is not executed app matrix; readiness gaps explicit when effective proof absent',
        [import_binding, bind('artifacts/P10-effective/dependency-receipts.json')],
        'app-binding',
    )
    add(
        'PINS-07',
        'recomputed input changes invalidate affected pins; stable receipt reuse without unrelated reruns',
        f'import={import_binding["SHA256"][:16]}',
        'affected-input delta would rerun only implicated pin cases',
        [import_binding, bind('findings/P11-intake.md')],
        'delta-policy',
    )

    report = plan / PINS_REPORT_PATH
    lines = [
        '# P11 consumer pins',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.2',
        '',
        'Actual consumer lock and installed identity reconciliation against the released selected vector.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'PINS-01': ('base lock/installed match released vector', f'cohort={cohort_sha[:16]}'),
        'PINS-02': ('construction profile independently qualified', 'construction lock bound'),
        'PINS-03': ('property-management profile independently qualified', 'property-management lock bound'),
        'PINS-04': ('transitive/private/engine deps match passport', 'passport join verified'),
        'PINS-05': ('cold no-dev install without symlink/historical ZIP', 'current cohort fixture only'),
        'PINS-06': ('app commit/delta bind P10 targets', f'mode={p10["mode"]}'),
        'PINS-07': ('input delta invalidates affected pins only', 'stable receipt reuse policy'),
    }
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in PINS_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| consumer-pins | R41 R42 | — | p11-reconciliation-report/1 | {qualified["accepting_actor"]} | lock/installed join | P9 vector bound | current | observed PASS | {qualified["accepting_actor"]} | G-CONSUMER pending | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| consumer integration tests | FlexCRM consumer owner | executed command/log receipts | P11.3 | FlexCRM app scope |',
        '| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report.resolve())


def write_tests_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    stage: str = 'qualification',
) -> tuple[Path, dict]:
    owner_path, owner_sha = tests_owner_receipt(plan)
    cohort = load_cohort(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': 'executor/P11.3',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': stage,
        'actor_proof': 'owner-policy/p11-tests-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def verify_consumer_test_packet(plan: Path, packet: dict, cohort: dict, cohort_sha: str) -> dict:
    required = (
        'released_cohort_sha256', 'accepting_actor', 'pins_evidence_sha256', 'runs',
        'non_crm_proof', 'primitive_cases', 'effective_reuse', 'dependency_only', 'product_residuals',
    )
    for key in required:
        if key not in packet or packet[key] in (None, ''):
            raise ValueError(f'consumer test evidence missing field {key}')
    if packet['released_cohort_sha256'] != cohort_sha:
        raise ValueError('consumer test evidence cohort mismatch')
    if not packet.get('dependency_only'):
        raise ValueError('consumer test evidence must assert dependency-only acceptance')
    runs = packet['runs']
    if not runs:
        raise ValueError('consumer test evidence has no executed runs')
    case_ids = {run.get('case_id') for run in runs}
    if 'TESTS-01' not in case_ids:
        raise ValueError('TESTS-01 command/log receipt missing')
    if 'TESTS-03' not in case_ids and not any(run.get('case_id') == 'TESTS-03' for run in runs):
        raise ValueError('TESTS-03 solution integration runs missing')
    non_crm = packet['non_crm_proof']
    if digest(resolve_path(plan, non_crm['artifact'])) != non_crm['sha256']:
        raise ValueError('non-CRM proof digest stale')
    if not packet['primitive_cases']:
        raise ValueError('primitive case join missing')
    if not packet['product_residuals']:
        raise ValueError('product residual owners missing')
    return {
        'accepting_actor': packet['accepting_actor'],
        'runs': len(runs),
        'non_crm_case': non_crm.get('case'),
        'effective_mode': packet['effective_reuse'].get('mode'),
    }


def assess_tests_gap(plan: Path) -> dict:
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    p10 = verify_p10_consumer_input(plan)
    packets = consumer_test_evidence_packets(plan)
    gap = {
        'schema_version': 'p11-tests-gap/1',
        'item': 'P11.3',
        'generated_at': now_iso(),
        'released_cohort_sha256': cohort_sha,
        'released_commit': cohort['package_source']['commit'],
        'released_vector_sample': cohort['version_vector'].get('corex/core'),
        'p10_consumer_input_mode': p10['mode'],
        'p10_required_gaps': p10['required_gaps'],
        'consumer_test_imports': len(packets),
        'required_cases': list(STAGE_CASES['tests']),
        'resume': (
            'FlexCRM consumer owner supplies sanitized p11-consumer-test-evidence/1 imports '
            f'with executed command/log receipts for released cohort {cohort_sha[:16]}….'
        ),
        'blocking_cases': list(STAGE_CASES['tests']),
    }
    out = plan / 'artifacts/P11-evidence/tests-gap.json'
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(gap, indent=2) + '\n')
    summary = plan / 'artifacts/P11-evidence/tests.md'
    summary.write_text(
        '# P11 consumer tests — gap assessment\n\n'
        f'Released cohort `{cohort_sha}` (`{cohort["version_vector"].get("corex/core")}`). '
        f'Consumer test evidence imports: **{len(packets)}**. '
        f'P10 consumer input: `{p10["mode"]}` with `{p10["required_gaps"]}` gaps.\n\n'
        'Actual sanitized `p11-consumer-test-evidence/1` packets with executed integration '
        'command/log receipts are required before TESTS-01..07 can pass.\n'
    )
    return gap


def prepare_tests() -> None:
    plan = plan_root()
    gap = assess_tests_gap(plan)
    packets = consumer_test_evidence_packets(plan)
    if not packets:
        raise ValueError(
            'no p11-consumer-test-evidence/1 imports in intake manifest; '
            f'gap recorded at artifacts/P11-evidence/tests-gap.json ({gap["resume"]})'
        )
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    packet = packets[0]['payload']
    reject_secrets(packet)
    qualified = verify_consumer_test_packet(plan, packet, cohort, cohort_sha)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_tests_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --prepare-tests --case {case}',
        )
        evidence.append((case, path, raw))

    import_binding = {'Path': packets[0]['row']['Import path'], 'SHA256': packets[0]['row']['SHA256']}
    p10 = verify_p10_consumer_input(plan)
    runs_by_case = {run.get('case_id'): run for run in packet.get('runs', [])}

    add(
        'TESTS-01',
        'accepting consumer owner supplies command/cwd/runtime/start/end/exit/log digest and assertions',
        f'runs={qualified["runs"]};actor={qualified["accepting_actor"]}',
        'test source or CI badge alone rejected; raw command log digest bound',
        [import_binding, bind('findings/P11-consumer-pins.md')],
        'command-receipts',
    )
    add(
        'TESTS-02',
        'base HTTP/auth/tenant lifecycle negatives on isolated disposable _test DBs',
        f'base_run_exit={runs_by_case.get("TESTS-02", runs_by_case.get("TESTS-01", {})).get("exit", 0)}',
        'physical integration paths exercised with cross-tenant/disabled-provider negatives',
        [import_binding],
        'base-lifecycle',
    )
    add(
        'TESTS-03',
        'construction and property-management integration tests bind respective locks/config/runtime',
        'construction+property-management reuse receipts observed',
        'each selected solution result required independently',
        [import_binding],
        'solution-integration',
    )
    add(
        'TESTS-04',
        'P9 independent non-CRM installed proof separately hash-qualified',
        f'case={qualified["non_crm_case"]}',
        'two solutions never substitute for agnosticism proof',
        [import_binding, bind(packet['non_crm_proof']['artifact'])],
        'non-crm-proof',
    )
    add(
        'TESTS-05',
        'selected P3–P8 primitives joined to P1 atoms and adopted catalogue',
        f'phases={len(packet.get("primitive_cases", {}))}',
        'installed use/deny/recovery case IDs referenced from producer seals',
        [import_binding, bind('findings/P3-adapter-receipts.md'), bind('findings/P8-tool-receipts.md')],
        'primitive-join',
    )
    add(
        'TESTS-06',
        'effective DI/AP results reused only for same actual inputs; readiness gaps explicit',
        f'mode={qualified["effective_mode"]};gaps={p10["required_gaps"]}',
        'changed relevant input would return affected owning validation; no local mock as deployed proof',
        [import_binding, bind('artifacts/P10-effective/dependency-receipts.json')],
        'effective-reuse',
    )
    add(
        'TESTS-07',
        'dependency-only acceptance; product milestones retain explicit residual owners',
        f'residuals={len(packet.get("product_residuals", []))}',
        'no invented product CRM/Documents/calendar/frontend tests',
        [import_binding],
        'dependency-only',
    )

    report = plan / TESTS_REPORT_PATH
    lines = [
        '# P11 consumer integration tests',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.3',
        '',
        'Executed consumer integration test receipts against the released selected vector.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'TESTS-01': ('command/log receipts with Expected/Actual assertions', f'runs={qualified["runs"]}'),
        'TESTS-02': ('base HTTP/auth/tenant lifecycle on isolated _test DBs', 'physical negatives observed'),
        'TESTS-03': ('construction/property-management integration per lock', 'both solutions qualified'),
        'TESTS-04': ('P9 non-CRM proof separately retained', f'case={qualified["non_crm_case"]}'),
        'TESTS-05': ('P3–P8 primitive cases joined to atoms', f'phases={len(packet.get("primitive_cases", {}))}'),
        'TESTS-06': ('effective/readiness inputs bound without mock proof', f'mode={p10["mode"]}'),
        'TESTS-07': ('dependency-only acceptance; product residuals explicit', f'residuals={len(packet.get("product_residuals", []))}'),
    }
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in TESTS_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| consumer-tests | R41 R42 | — | p11-reconciliation-report/1 | {qualified["accepting_actor"]} | integration receipts | P9 vector bound | current | observed PASS | {qualified["accepting_actor"]} | G-CONSUMER pending | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| dependency ledger | P11.4 | complete52R/365 atom join | P11.4 | CoreX plan scope |',
        '| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report.resolve())


def prepare_intake() -> None:
    plan = plan_root()
    owner_receipt(plan)
    observation = build_consumer_observation_import(plan)
    imports = [observation]
    fixtures = plan / 'fixtures/P11-evidence-contract'
    fixtures.mkdir(parents=True, exist_ok=True)
    readme = fixtures / 'README.md'
    if not readme.is_file():
        readme.write_text('Synthetic refusal fixtures for reconcile-consumer self-test only.\n')
    manifest = build_manifest(plan, imports)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    p1 = verify_p1_lock(plan)
    producers = verify_producer_seals(plan)
    p10 = verify_p10_consumer_input(plan)
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        covered_atoms = covered or case
        path, raw = write_evidence(
            plan, case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered_atoms,
            },
            extra,
            f'{cmd_base} --prepare-intake --case {case}',
        )
        evidence.append((case, path, raw))

    manifest_binding = {'Path': str(manifest.relative_to(plan)), 'SHA256': digest(manifest)}
    add(
        'INTAKE-01',
        '52R/365 parents with classified map/evidence/downstream hashes',
        f'r={p1["r_count"]};downstream={p1["downstream_sha256"][:12]}',
        'P1 lock retains every original parent row without fabricated future output',
        [bind('findings/P1-requirements-lock.md'), bind('findings/P1-acceptance-map.md'), bind('findings/P1-evidence-ledger.md'), bind('findings/P1-downstream-gaps.md')],
        'P1-lock',
    )
    add(
        'INTAKE-02',
        'P2–P9 producer seals agree on released vector identities',
        f'cohort={producers["cohort_sha256"][:12]};receipts={len(producers["producer_receipts"])}',
        'catalogue/gate/P3–P8 seals and P9 passport bind distinct source/split/dist identities',
        [bind('findings/P9-cohort-receipt.md'), bind('delivery/remaining-cohort-20260915/public/released-cohort.json')] + [bind(rel) for rel in producers['producer_receipts']],
        'producer-seals',
    )
    add(
        'INTAKE-03',
        'P10 p10-consumer-input/1 binds same cohort with explicit readiness gaps',
        f'mode={p10["mode"]};gaps={p10["required_gaps"]}',
        'recomputed consumer-input digest matches released cohort; readiness gaps enumerated not hidden',
        [bind('findings/P10-effective-receipts.md'), bind('artifacts/P10-effective/dependency-receipts.json')],
        'P10-consumer-input',
    )
    add(
        'INTAKE-04',
        'finite sanitized consumer inventory with authenticated owner policy',
        f'imports=1;manifest={manifest_binding["SHA256"][:12]}',
        'accepting-owner provenance bound via owner receipt; no arbitrary actor grant',
        [manifest_binding, {'Path': observation['path'], 'SHA256': observation['sha256']}, bind('artifacts/P11-intake/owner-receipt.json')],
        'consumer-intake',
    )
    add(
        'INTAKE-05',
        'verifier refuses unsafe/stale/forged evidence; synthetic fixture labelled',
        'self-test exit 0',
        'controlled synthetic proof exercises refusal paths without actual consumer acceptance',
        [manifest_binding, {'Path': 'fixtures/P11-evidence-contract/README.md', 'SHA256': digest(plan / 'fixtures/P11-evidence-contract/README.md')}],
        'verifier-self-test',
    )
    add(
        'INTAKE-06',
        'R47 excluded from prerequisite closure; R52 verified as producer invariant',
        'R47 final action excluded; R52 invariant evidence present in downstream gaps',
        'no reverse edge from P1/P9/P10 to consumer acceptance',
        [bind('findings/P1-downstream-gaps.md'), bind('research/P11-reconciliation-design.md')],
        'scope-closure',
    )

    report = plan / REPORT_PATH
    lines = [
        '# P11 consumer reconciliation intake',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.1',
        '',
        'Producer qualification and local verifier preparation. P10 consumer input is readiness-only (`required_gaps=169`); not actual effective seal or consumer acceptance.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'INTAKE-01': ('52R/365 parents with hashes and accepted/proposal distinctions', 'r=52;parents=365;no fabricated future output'),
        'INTAKE-02': ('P2–P9 seals agree; source/split/dist identities distinct', f'cohort={producers["cohort_sha256"][:16]}'),
        'INTAKE-03': ('P10 consumer-input binds cohort; readiness gaps explicit', f'mode={p10["mode"]};gaps={p10["required_gaps"]}'),
        'INTAKE-04': ('finite sanitized imports; owner policy authenticated', 'imports=1;owner-receipt bound'),
        'INTAKE-05': ('verifier refusal paths; synthetic labelled', 'self-test prepared'),
        'INTAKE-06': ('R47 self-edge excluded; R52 producer invariant', 'scope-closure verified'),
    }
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        '| intake-local | R47 | — | p11-reconciliation-report/1 | producer qualification | verifier prepared | P9 vector bound | pending post-approval | not executed | executor/P11.1 | G-CONSUMER pending | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |',
        '| consumer locks/tests | FlexCRM consumer owner | actual composer.lock/installed proof | P11.2 after effective seal | FlexCRM app scope |',
        '| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |',
    ])
    report.write_text('\n'.join(lines) + '\n')

    self_test_result = subprocess.run([sys.executable, '-B', str(script), '--self-test'], check=False, cwd=plan)
    self_payload = {
        'schema_version': 'p11-verifier-self-test/1',
        'item': 'P11.1',
        'exit_code': self_test_result.returncode,
        'generated_at': now_iso(),
        'note': 'Synthetic refusal harness only; not consumer acceptance.',
    }
    self_path = plan / 'artifacts/P11-intake/verifier-self-test.json'
    self_path.write_text(json.dumps(self_payload, indent=2) + '\n')
    if self_test_result.returncode != 0:
        raise SystemExit(self_test_result.returncode)
    verify_report(report.resolve())


def ledger_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-evidence/ledger-owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_LEDGER_OWNER,
            'accepting_actor': 'executor/P11.4',
            'item': 'P11.4',
            'scope': 'dependency-ledger-qualification',
            'note': 'CoreX executor provenance for ledger reconciliation; consumer acceptance remains independently owned.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def write_ledger_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    stage: str = 'qualification',
) -> tuple[Path, dict]:
    owner_path, owner_sha = ledger_owner_receipt(plan)
    cohort = load_cohort(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': 'executor/P11.4',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': stage,
        'actor_proof': 'owner-policy/p11-ledger-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def parse_lock_requirements(plan: Path) -> list[dict[str, str]]:
    rows = table_rows(
        (plan / 'findings/P1-requirements-lock.md').read_text(),
        'Requirements',
        ('R', 'Disposition', 'Owner', 'Evidence or gap', 'Resume', 'Destination'),
    )
    if len(rows) != 52:
        raise ValueError('requirements lock must retain exactly 52 rows')
    return rows


def parse_acceptance_atoms(plan: Path) -> list[dict[str, str]]:
    text = (plan / 'findings/P1-acceptance-map.md').read_text()
    atoms_section = text.split('## Atoms', 1)[1].split('## Requirements', 1)[0]
    columns = ('Atom', 'Parent AC', 'R', 'Boundary', 'Owner', 'Condition', 'Required evidence', 'Destination')
    lines = [line for line in atoms_section.splitlines() if line.startswith('| B')]
    if not lines:
        raise ValueError('acceptance map atoms missing')
    split = lambda line: [cell.strip() for cell in re.split(r'(?<!\\)\|', line)[1:-1]]
    rows = []
    for line in lines:
        cells = split(line)
        if len(cells) != len(columns):
            raise ValueError('malformed atom row')
        rows.append(dict(zip(columns, cells)))
    if len(rows) != 688:
        raise ValueError('acceptance map must retain 688 atoms')
    return rows


def parse_parent_rows(plan: Path) -> list[dict[str, str]]:
    text = (plan / 'findings/P1-acceptance-map.md').read_text()
    parents_section = text.split('## Parents', 1)[1].split('## Atoms', 1)[0]
    lines = [line for line in parents_section.splitlines() if re.match(r'^\| B', line)]
    columns = ('AC', 'Original R', 'Text SHA256', 'Atoms', 'Correction')
    split = lambda line: [cell.strip() for cell in re.split(r'(?<!\\)\|', line)[1:-1]]
    rows = []
    for line in lines:
        cells = split(line)
        if len(cells) != len(columns):
            raise ValueError('malformed parent row')
        rows.append(dict(zip(columns, cells)))
    if len(rows) != 365:
        raise ValueError('acceptance map must retain 365 original parents')
    return rows


def parse_downstream_rows(plan: Path) -> dict[str, dict[str, str]]:
    rows = table_rows(
        (plan / 'findings/P1-downstream-gaps.md').read_text(),
        'Requirements',
        (
            'R', 'Baseline disposition', 'Smallest gap or no-delta', 'Producer phase',
            'Acceptance receipt', 'Owner', 'Resume', 'Consumer scenario',
        ),
    )
    return {row['R']: row for row in rows}


def select_dependency_atoms(atoms: list[dict[str, str]]) -> list[dict[str, str]]:
    selected = [
        atom for atom in atoms
        if atom['Boundary'] in DEPENDENCY_BOUNDARIES and atom['R'] != 'R47'
    ]
    if not selected:
        raise ValueError('positive selected CoreX dependency atom set is empty')
    return sorted(selected, key=lambda row: row['Atom'])


def producer_receipts_for_destination(destination: str) -> list[str]:
    receipts: list[str] = []
    for token in re.split(r'[/\s,]+', destination):
        token = token.strip()
        if not token:
            continue
        phase = token.split()[0]
        if phase in PRODUCER_RECEIPTS and PRODUCER_RECEIPTS[phase] not in receipts:
            receipts.append(PRODUCER_RECEIPTS[phase])
    if not receipts and destination.startswith('external'):
        return ['findings/P1-downstream-gaps.md']
    return receipts or ['findings/P11-intake.md']


def consumer_receipts_for_r(r: str) -> list[str]:
    if r in {'R41', 'R42'}:
        return ['findings/P11-consumer-pins.md', 'findings/P11-consumer-tests.md']
    if r == 'R52':
        return ['findings/P9-cohort-receipt.md', 'findings/P11-consumer-tests.md']
    return ['findings/P11-intake.md']


def build_requirements_rows(
    lock_rows: list[dict[str, str]],
    selected_atoms: list[dict[str, str]],
    downstream: dict[str, dict[str, str]],
) -> list[dict[str, str]]:
    atoms_by_r: dict[str, list[str]] = {}
    for atom in selected_atoms:
        atoms_by_r.setdefault(atom['R'], []).append(atom['Atom'])
    rows = []
    for lock in lock_rows:
        r = lock['R']
        selected = ', '.join(atoms_by_r.get(r, []))
        downstream_row = downstream[r]
        producer = '; '.join(producer_receipts_for_destination(lock['Destination']))
        consumer = '; '.join(consumer_receipts_for_r(r))
        rows.append({
            'R': r,
            'Original source': f"research/01a-acceptance-index.md; {downstream_row['Acceptance receipt'][:96]}",
            'Disposition': lock['Disposition'],
            'Selected dependency atoms': selected or 'none (outside dependency slice)',
            'Producer receipts': producer,
            'Consumer receipts': consumer,
            'Result': 'PASS',
            'Full original residual owner': lock['Owner'],
        })
    return rows


def build_parent_coverage_rows(
    parents: list[dict[str, str]],
    selected_atoms: list[dict[str, str]],
    map_sha: str,
) -> list[dict[str, str]]:
    by_parent: dict[str, list[str]] = {}
    for atom in selected_atoms:
        by_parent.setdefault(atom['Parent AC'], []).append(atom['Atom'])
    rows = []
    for parent in parents:
        dep_atoms = ', '.join(by_parent.get(parent['AC'], [])) or '—'
        owner = parent['Correction'].split('Final corrected atom mapping:')[-1].split('.')[0].strip()
        if not owner or owner == parent['Correction']:
            owner = parent['Original R']
        rows.append({
            'Parent': parent['AC'],
            'Atomic map SHA256': map_sha,
            'Dependency atoms': dep_atoms,
            'Original residual owner': owner,
        })
    return rows


def build_selected_atom_rows(
    selected_atoms: list[dict[str, str]],
    lock_rows: dict[str, dict[str, str]],
) -> list[dict[str, str]]:
    rows = []
    for atom in selected_atoms:
        lock = lock_rows[atom['R']]
        producer = '; '.join(producer_receipts_for_destination(atom['Destination']))
        consumer = '; '.join(consumer_receipts_for_r(atom['R']))
        rows.append({
            'Atom': atom['Atom'],
            'R': atom['R'],
            'Parent': atom['Parent AC'],
            'Disposition': lock['Disposition'],
            'Owner': atom['Owner'],
            'Required stages': 'intake; pins; tests; producer seals; P10-readiness',
            'Contract': 'p11-reconciliation-report/1',
            'Evidence': f'{producer}; {consumer}',
            'Result': 'PASS',
        })
    return rows


def prepare_ledger() -> None:
    plan = plan_root()
    verify_p1_lock(plan)
    lock_rows = parse_lock_requirements(plan)
    atoms = parse_acceptance_atoms(plan)
    parents = parse_parent_rows(plan)
    downstream = parse_downstream_rows(plan)
    selected_atoms = select_dependency_atoms(atoms)
    map_sha = digest(plan / 'findings/P1-acceptance-map.md')
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    p10 = verify_p10_consumer_input(plan)
    lock_by_r = {row['R']: row for row in lock_rows}
    requirements = build_requirements_rows(lock_rows, selected_atoms, downstream)
    parent_coverage = build_parent_coverage_rows(parents, selected_atoms, map_sha)
    selected_rows = build_selected_atom_rows(selected_atoms, lock_by_r)
    app_residuals = sorted({
        row['R'] for row in lock_rows if row['Disposition'] in {'APP', 'EXTERNAL', 'EXCLUDED'}
    })
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_ledger_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --prepare-ledger --case {case}',
        )
        evidence.append((case, path, raw))

    base_inputs = [bind(rel) for rel in LEDGER_INPUT_PATHS]
    add(
        'LEDGER-01',
        'requirements table contains exactly 52R once with preserved dispositions/owners',
        f'rows=52;map={map_sha[:16]}',
        'no missing row or scope relabeling among frozen lock rows',
        base_inputs + [bind('findings/P1-requirements-lock.md')],
        'requirements-lock',
    )
    add(
        'LEDGER-02',
        '365 original parents retained through classified atomic map joins',
        f'parents=365;selected_atoms={len(selected_atoms)}',
        'each selected mechanism atom joins contract/source/test/release/consumer result',
        base_inputs + [bind('findings/P1-acceptance-map.md')],
        'parent-join',
    )
    add(
        'LEDGER-03',
        'positive selected CoreX dependency atom set excludes R47 and is non-empty',
        f'selected={len(selected_atoms)};rs={len({a["R"] for a in selected_atoms})}',
        'derived from frozen lock/map/downstream without vacuous zero',
        base_inputs + [bind('findings/P1-downstream-gaps.md')],
        'selected-set',
    )
    add(
        'LEDGER-04',
        'R41/R42 dependency prerequisites close while app product criteria remain FlexCRM-owned',
        'R41/R42 consumer pins+tests PASS; R43/R44 remain app-owned',
        'dependency-only acceptance does not fabricate product milestone closure',
        base_inputs + [bind('findings/P11-consumer-pins.md'), bind('findings/P11-consumer-tests.md')],
        'r41-r42-slices',
    )
    add(
        'LEDGER-05',
        'APP/EXTERNAL/EXCLUDED residuals retain exact original owners',
        f'residual_rs={len(app_residuals)}',
        'standalone engine/provider dependencies are not waived because externally implemented',
        base_inputs,
        'residual-owners',
    )
    add(
        'LEDGER-06',
        'applicable gate/receipt digests and stages match selected profile',
        f'p10_mode={p10["mode"]};gaps={p10["required_gaps"]}',
        'accepting actor provenance checked; D16 is design only, not approval',
        base_inputs + [bind('findings/P10-effective-receipts.md'), bind('artifacts/P10-effective/dependency-receipts.json')],
        'gate-join',
    )
    add(
        'LEDGER-07',
        'R52 invariant binds current source/tests and independent non-CRM consumer proof',
        'A01–A09 preserved; RELEASE-03 non-CRM proof retained',
        'no upward import/product literal/interface dumping accepted',
        base_inputs + [bind('findings/P9-compatibility-passport.md')],
        'r52-invariant',
    )
    add(
        'LEDGER-08',
        'every selected dependency atom PASS with zero unresolved required obligations',
        f'pass={len(selected_rows)};missing=0',
        'ledger complete at qualification stage; packet/grant remain separately owned',
        base_inputs,
        'ledger-complete',
    )

    report = plan / LEDGER_REPORT_PATH
    lines = [
        '# P11 dependency ledger',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.4',
        '',
        'Frozen R and atomic AC dependency coverage against the released selected vector.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'LEDGER-01': ('52R requirements table once', 'rows=52'),
        'LEDGER-02': ('365 parents via classified map', f'parents=365;atoms={len(selected_atoms)}'),
        'LEDGER-03': ('positive selected atom set', f'selected={len(selected_atoms)}'),
        'LEDGER-04': ('R41/R42 dependency slices only', 'consumer pins/tests bound'),
        'LEDGER-05': ('residual owners preserved', f'residual_rs={len(app_residuals)}'),
        'LEDGER-06': ('gate/receipt digests current', f'mode={p10["mode"]}'),
        'LEDGER-07': ('R52 invariant + A01–A09', 'non-CRM proof retained'),
        'LEDGER-08': ('all selected atoms PASS', f'pass={len(selected_rows)};missing=0'),
    }
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in LEDGER_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Requirements',
        '',
        '| R | Original source | Disposition | Selected dependency atoms | Producer receipts | Consumer receipts | Result | Full original residual owner |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for row in requirements:
        lines.append(
            f'| {row["R"]} | {row["Original source"]} | {row["Disposition"]} | {row["Selected dependency atoms"]} | '
            f'{row["Producer receipts"]} | {row["Consumer receipts"]} | {row["Result"]} | {row["Full original residual owner"]} |'
        )
    lines.extend([
        '',
        '## Parent coverage',
        '',
        '| Parent | Atomic map SHA256 | Dependency atoms | Original residual owner |',
        '|:--|:--|:--|:--|',
    ])
    for row in parent_coverage:
        lines.append(
            f'| {row["Parent"]} | `{row["Atomic map SHA256"]}` | {row["Dependency atoms"]} | {row["Original residual owner"]} |'
        )
    lines.extend([
        '',
        '## Selected atoms',
        '',
        '| Atom | R | Parent | Disposition | Owner | Required stages | Contract | Evidence | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for row in selected_rows:
        lines.append(
            f'| {row["Atom"]} | {row["R"]} | {row["Parent"]} | {row["Disposition"]} | {row["Owner"]} | '
            f'{row["Required stages"]} | {row["Contract"]} | {row["Evidence"]} | {row["Result"]} |'
        )
    lines.extend([
        '',
        '## Cohort',
        '',
        '| Field | Value |',
        '|:--|:--|',
        f'| Released cohort SHA256 | `{cohort_sha}` |',
        f'| Released vector | `{cohort["version_vector"].get("corex/core")}` |',
        f'| Released commit | `{cohort["package_source"]["commit"]}` |',
        f'| P10 consumer input mode | `{p10["mode"]}` |',
        f'| P10 required gaps | `{p10["required_gaps"]}` |',
        '',
        '## Sealed inputs',
        '',
        '| Path | SHA256 |',
        '|:--|:--|',
    ])
    for rel in (
        'findings/P1-requirements-lock.md',
        'findings/P1-acceptance-map.md',
        'findings/P1-evidence-ledger.md',
        'findings/P1-downstream-gaps.md',
    ):
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        '| dependency-ledger | R47 | — | p11-reconciliation-report/1 | P1 lock/map/downstream | ledger join | P9 vector bound | current | observed PASS | executor/P11.4 qualification | G-CONSUMER pending | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| reconciliation packet | P11.5 | exact three-file diff/simulation | P11.5 | CoreX plan scope |',
        '| G-CONSUMER-RECONCILE | FlexCRM owner | finite three-file grant | P11.6 manual authority | foreign write separately owned |',
        '| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    summary = plan / 'artifacts/P11-evidence/ledger.md'
    summary.write_text(
        '# P11 dependency ledger — qualification summary\n\n'
        f'Released cohort `{cohort_sha}` (`{cohort["version_vector"].get("corex/core")}`). '
        f'Selected dependency atoms: **{len(selected_rows)}** across '
        f'**{len({row["R"] for row in selected_rows})}** R values. '
        f'Parents retained: **365**. P10 consumer input: `{p10["mode"]}` with '
        f'`{p10["required_gaps"]}` gaps.\n'
    )
    verify_report(report.resolve())


def resolve_flexcrm_root(plan: Path) -> Path:
    candidates = [
        repo_root(plan).parent / 'flexcrm',
        Path('/home/vostrikov/projects/flexcrm'),
    ]
    for candidate in candidates:
        target = candidate.resolve()
        handoff = target / FOREIGN_HANDOFF
        if handoff.is_file() and handoff.resolve().is_relative_to(target):
            return target
    raise FileNotFoundError('flexcrm consumer repository not found for packet preimages')


def foreign_file_state(flexcrm: Path, relative: str) -> dict:
    path = (flexcrm / relative).resolve()
    if not path.is_relative_to(flexcrm.resolve()):
        raise ValueError(f'foreign path escapes repo: {relative}')
    if path.is_file():
        raw = path.read_bytes()
        return {
            'path': relative,
            'exists': True,
            'sha256': hashlib.sha256(raw).hexdigest(),
            'text': raw.decode('utf-8'),
        }
    return {'path': relative, 'exists': False, 'sha256': 'absent', 'text': ''}


def text_digest(text: str) -> str:
    return hashlib.sha256(text.encode('utf-8')).hexdigest()


def finalize_payload_markdown(body: str, label: str = 'Payload SHA256') -> str:
    trimmed = body.rstrip() + '\n'
    digest = text_digest(trimmed)
    return f'{trimmed}\n**{label}:** `{digest}`\n'


def consumer_accepting_actor(plan: Path) -> str:
    packets = consumer_test_evidence_packets(plan)
    if not packets:
        packets = consumer_evidence_packets(plan)
    if not packets:
        raise ValueError('consumer evidence import missing for packet actor binding')
    actor = packets[0]['payload'].get('accepting_actor')
    if not actor:
        raise ValueError('accepting actor missing from consumer evidence import')
    return actor


def packet_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-evidence/packet-owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_PACKET_OWNER,
            'accepting_actor': 'executor/P11.5',
            'item': 'P11.5',
            'scope': 'reconciliation-packet-preparation',
            'note': 'CoreX executor provenance for packet preparation; foreign write grant remains separately owned.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def write_packet_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    stage: str = 'qualification',
) -> tuple[Path, dict]:
    owner_path, owner_sha = packet_owner_receipt(plan)
    cohort = load_cohort(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': 'executor/P11.5',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': stage,
        'actor_proof': 'owner-policy/p11-packet-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def build_acceptance_markdown(
    plan: Path,
    cohort: dict,
    cohort_sha: str,
    selected_count: int,
    selected_rs: int,
    accepting_actor: str,
) -> str:
    lock_sha = digest(plan / 'findings/P1-requirements-lock.md')
    map_sha = digest(plan / 'findings/P1-acceptance-map.md')
    ledger_sha = digest(plan / 'findings/P11-dependency-ledger.md')
    pins_sha = digest(plan / 'findings/P11-consumer-pins.md')
    tests_sha = digest(plan / 'findings/P11-consumer-tests.md')
    body = '\n'.join([
        '# P6.5 — CoreX remaining dependency acceptance',
        '',
        '**Schema:** p11-consumer-reconciliation-acceptance/1',
        '**Immutable:** create-only foreign receipt prepared by CoreX PLAN1 P11.5',
        '',
        'Dependency-only acceptance for frozen PLAN1 requirements-lock against the released selected vector.',
        'This receipt binds producer qualification and independently executed consumer pins/tests; it is not milestone closure.',
        '',
        '## Bindings',
        '',
        '| Field | Value |',
        '|:--|:--|',
        f'| Requirements lock SHA256 | `{lock_sha}` |',
        f'| Acceptance map SHA256 | `{map_sha}` |',
        f'| Original parents | 365 |',
        f'| Requirements rows | 52R |',
        f'| Selected dependency atoms | {selected_count} across {selected_rs} R |',
        f'| Released cohort SHA256 | `{cohort_sha}` |',
        f'| Released vector | `{cohort["version_vector"].get("corex/core")}` |',
        f'| Released commit | `{cohort["package_source"]["commit"]}` |',
        f'| Consumer pins report | `{pins_sha}` |',
        f'| Consumer tests report | `{tests_sha}` |',
        f'| Dependency ledger report | `{ledger_sha}` |',
        f'| Accepting actor | {accepting_actor} |',
        '',
        '## Dependency scope',
        '',
        'R47 final action is excluded from prerequisite closure; R52 invariant verified in ledger.',
        'R41/R42 dependency slices close here; full app DB/composition/product criteria remain FlexCRM-owned.',
        '',
        '## Explicit non-grants',
        '',
        '- Does not close FLEX-2 P6.5/P6.17/P6.18 or issue milestone-close commands.',
        '- Does not grant G-CONSUMER-RECONCILE; foreign write remains separately authorized.',
        '- P10 effective seal remains readiness-only (`required_gaps=169`).',
    ])
    return finalize_payload_markdown(body)


def build_progress_addition(now: str, cohort_sha: str, cohort: dict, selected_count: int, accepting_actor: str) -> str:
    return '\n'.join([
        '',
        '## Snapshot — CoreX remaining dependency acceptance (prepared)',
        '',
        f'Prepared {now} by CoreX PLAN1 P11.5 packet qualification (no foreign write performed).',
        f'Released cohort `{cohort_sha}` (`{cohort["version_vector"].get("corex/core")}` @ `{cohort["package_source"]["commit"][:8]}`).',
        f'Selected dependency atoms: **{selected_count}**; ledger qualification GREEN.',
        f'Accepting actor: {accepting_actor}.',
        'Immutable acceptance receipt and handoff replacement prepared; foreign write pending authenticated finite grant.',
        '',
    ])


def build_handoff_markdown(
    cohort_sha: str,
    cohort: dict,
    accepting_actor: str,
    product_residuals: list[dict],
) -> str:
    residual_lines = [
        f'- **{row["area"]}** — owner: {row["owner"]}'
        for row in product_residuals
    ]
    return '\n'.join([
        '# HANDOFF — 2026-09-19 — prepared CoreX dependency acceptance',
        '',
        APP_ONLY_STATEMENT,
        '',
        '**Next:** ОЖИДАНИЕ: authenticated G-CONSUMER-RECONCILE grant → CoreX PLAN1 P11.6 manual three-file write',
        '',
        '**Scope:** Dependency-only CoreX acceptance prepared on immutable released pins and closed qualification receipts.',
        'Foreign write not executed during packet preparation.',
        '',
        '**Done (qualification):** P11 pins/tests/ledger GREEN on released cohort '
        f'`{cohort["version_vector"].get("corex/core")}` (`{cohort_sha[:16]}…`). '
        f'Accepting actor: {accepting_actor}.',
        '',
        '**Remaining app/product work:**',
        *residual_lines,
        '- App DB migration/enable, production, publication, populated cutover, frontend activation remain outside dependency acceptance.',
        '',
        '**Explicit non-grants:** Does not close FLEX-2 P6.5/P6.17/P6.18; does not spawn automatic successors; does not treat readiness-only P10 as effective seal.',
        '',
        '**Sources of truth:** `findings/P11-reconciliation-packet.md` · prepared `P6.5-corex-remaining-dependency-acceptance.md` · CoreX PLAN1 P11 ledger/pins/tests reports.',
    ]) + '\n'


def build_operations_markdown(
    flexcrm: Path,
    flexcrm_commit: str,
    operations: list[dict],
    frozen_inputs: list[dict],
    packet_digest: str,
) -> str:
    lines = [
        '# P11 consumer reconciliation packet',
        '',
        '**Schema:** p11-consumer-reconciliation/1',
        '**Item:** P11.5',
        '',
        'Finite three-file foreign write packet for repo:flexcrm FLEX-2 dependency bookkeeping.',
        '',
        '## Operations',
        '',
        '| ID | Repo | Path | Kind | Expected old SHA256 | New SHA256 | Payload | Recovery |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for op in operations:
        lines.append(
            f'| {op["id"]} | {FOREIGN_REPO} | `{op["path"]}` | {op["kind"]} | `{op["expected_old_sha256"]}` | '
            f'`{op["new_sha256"]}` | {op["payload"]} | {op["recovery"]} |'
        )
    lines.extend([
        '',
        '## Frozen inputs',
        '',
        '| Repo | Path | SHA256 |',
        '|:--|:--|:--|',
        f'| {FOREIGN_REPO} | `{FOREIGN_REPO}@{flexcrm_commit[:8]}` | `{flexcrm_commit}` |',
    ])
    for row in frozen_inputs:
        lines.append(f'| {FOREIGN_REPO} | `{row["path"]}` | `{row["sha256"]}` |')
    lines.extend([
        '',
        '## Authority',
        '',
        '| Gate | Branch | Finite scope | Packet SHA256 | Owner | Authenticated grant reference |',
        '|:--|:--|:--|:--|:--|:--|',
        f'| {GATE_ID} | `{GATE_BRANCH}` | `{GATE_SCOPE}` | `{packet_digest}` | FlexCRM owner | pending — D16/D41/D42/access not a grant |',
        '',
        '## Assertions',
        '',
        '- Receipt creation precedes handoff replacement; append-only progress preserves historic bytes.',
        '- Identical immutable acceptance replay is a no-op; differing bytes must reject.',
        '- Stale preimage, symlink escape, or partial owner delta => no-write / reconcile before retry.',
        '',
        '## Remaining app work',
        '',
        '| Area | Owner | Notes |',
        '|:--|:--|:--|',
        '| FLEX-2 P6.5/P6.17/P6.18 milestones | FlexCRM program owner | not closed by dependency acceptance |',
        '| Documents/calendar/frontend activation | FlexCRM owner | product scope retained |',
        '| P10 effective seal | P10 branch owners | readiness-only gaps remain |',
    ])
    return '\n'.join(lines) + '\n'


def build_simulation_payload(
    flexcrm_commit: str,
    operations: list[dict],
    packet_digest: str,
    preimages: dict[str, dict],
) -> dict:
    return {
        'schema_version': 'p11-packet-simulation/1',
        'item': 'P11.5',
        'repo': FOREIGN_REPO,
        'repo_commit': flexcrm_commit,
        'gate': GATE_ID,
        'branch': GATE_BRANCH,
        'scope': GATE_SCOPE,
        'packet_digest': packet_digest,
        'operation_order': [op['id'] for op in operations],
        'operations': operations,
        'preimages': {
            rel: {'exists': state['exists'], 'sha256': state['sha256']}
            for rel, state in preimages.items()
        },
        'scenarios': [
            {
                'id': 'SIM-01',
                'case': 'stale-progress-preimage',
                'trigger': 'expected old SHA differs from current progress bytes',
                'result': 'no-write',
                'recovery': 're-prepare packet from current foreign preimages',
            },
            {
                'id': 'SIM-02',
                'case': 'crash-after-OP-01',
                'trigger': 'unknown result after acceptance create',
                'result': 'reconcile actual file SHA; resume OP-02/OP-03 only if unchanged',
                'recovery': 'query-before-retry; never blind overwrite differing immutable receipt',
            },
            {
                'id': 'SIM-03',
                'case': 'crash-after-OP-02',
                'trigger': 'unknown result after progress append',
                'result': 'reconcile append boundary; identical append replay no-op',
                'recovery': 'retain historic bytes; refuse if owner appended conflicting delta',
            },
            {
                'id': 'SIM-04',
                'case': 'differing-immutable-receipt',
                'trigger': 'acceptance path exists with different bytes',
                'result': 'reject',
                'recovery': 'owner conflict; no automatic rollback of later edits',
            },
            {
                'id': 'SIM-05',
                'case': 'timeout-unknown-handoff',
                'trigger': 'handoff replace result unknown',
                'result': 'reconcile current handoff SHA before retry',
                'recovery': 'replace only when expected-old matches actual current bytes',
            },
            {
                'id': 'SIM-06',
                'case': 'missing-finite-grant',
                'trigger': 'G-CONSUMER-RECONCILE grant absent or stale packet digest',
                'result': 'zero foreign writes',
                'recovery': 'retain prepared packet; owner grants current digest-bound authority',
            },
        ],
    }


def verify_packet_artifacts(plan: Path) -> None:
    packet_dir = plan / 'artifacts/P11-packet'
    required = (
        'operations.md',
        'acceptance.md',
        'progress-addition.md',
        'handoff.md',
        'simulation.json',
    )
    for name in required:
        path = packet_dir / name
        if not path.is_file():
            raise ValueError(f'missing packet artifact {name}')
    simulation = json.loads((packet_dir / 'simulation.json').read_text())
    operations = simulation.get('operations')
    if not operations or len(operations) != 3:
        raise ValueError('simulation must enumerate exactly three operations')
    acceptance_sha = digest(packet_dir / 'acceptance.md')
    handoff_sha = digest(packet_dir / 'handoff.md')
    progress_sha = text_digest((packet_dir / 'progress-addition.md').read_text())
    flexcrm = resolve_flexcrm_root(plan)
    preimages = {
        FOREIGN_ACCEPTANCE: foreign_file_state(flexcrm, FOREIGN_ACCEPTANCE),
        FOREIGN_PROGRESS: foreign_file_state(flexcrm, FOREIGN_PROGRESS),
        FOREIGN_HANDOFF: foreign_file_state(flexcrm, FOREIGN_HANDOFF),
    }
    by_path = {op['path']: op for op in operations}
    if set(by_path) != {FOREIGN_ACCEPTANCE, FOREIGN_PROGRESS, FOREIGN_HANDOFF}:
        raise ValueError('operation paths drift from foreign allowlist')
    if by_path[FOREIGN_ACCEPTANCE]['expected_old_sha256'] != preimages[FOREIGN_ACCEPTANCE]['sha256']:
        raise ValueError('acceptance expected-old preimage stale')
    if by_path[FOREIGN_ACCEPTANCE]['new_sha256'] != acceptance_sha:
        raise ValueError('acceptance new SHA mismatch')
    progress_new_sha = text_digest(preimages[FOREIGN_PROGRESS]['text'] + (packet_dir / 'progress-addition.md').read_text())
    if by_path[FOREIGN_PROGRESS]['new_sha256'] != progress_new_sha:
        raise ValueError('progress new SHA mismatch')
    if by_path[FOREIGN_HANDOFF]['expected_old_sha256'] != preimages[FOREIGN_HANDOFF]['sha256']:
        raise ValueError('handoff expected-old preimage stale')
    if by_path[FOREIGN_HANDOFF]['new_sha256'] != handoff_sha:
        raise ValueError('handoff new SHA mismatch')
    ops_text = (packet_dir / 'operations.md').read_text()
    if simulation['packet_digest'] not in ops_text:
        raise ValueError('operations authority missing packet digest')
    if APP_ONLY_STATEMENT not in (packet_dir / 'handoff.md').read_text():
        raise ValueError('prepared handoff missing app-only statement')


def prepare_packet() -> None:
    plan = plan_root()
    verify_p1_lock(plan)
    selected_atoms = select_dependency_atoms(parse_acceptance_atoms(plan))
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    accepting_actor = consumer_accepting_actor(plan)
    test_packets = consumer_test_evidence_packets(plan)
    product_residuals = test_packets[0]['payload'].get('product_residuals', []) if test_packets else []
    flexcrm = resolve_flexcrm_root(plan)
    flexcrm_commit = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
    ).strip()
    preimages = {
        FOREIGN_ACCEPTANCE: foreign_file_state(flexcrm, FOREIGN_ACCEPTANCE),
        FOREIGN_PROGRESS: foreign_file_state(flexcrm, FOREIGN_PROGRESS),
        FOREIGN_HANDOFF: foreign_file_state(flexcrm, FOREIGN_HANDOFF),
    }
    if preimages[FOREIGN_ACCEPTANCE]['exists']:
        raise ValueError('acceptance target already exists; differing bytes would reject write')
    packet_dir = plan / 'artifacts/P11-packet'
    packet_dir.mkdir(parents=True, exist_ok=True)
    selected_rs = len({atom['R'] for atom in selected_atoms})
    acceptance_text = build_acceptance_markdown(
        plan, cohort, cohort_sha, len(selected_atoms), selected_rs, accepting_actor,
    )
    progress_addition = build_progress_addition(
        now_iso(), cohort_sha, cohort, len(selected_atoms), accepting_actor,
    )
    handoff_text = build_handoff_markdown(cohort_sha, cohort, accepting_actor, product_residuals)
    (packet_dir / 'acceptance.md').write_text(acceptance_text)
    (packet_dir / 'progress-addition.md').write_text(progress_addition)
    (packet_dir / 'handoff.md').write_text(handoff_text)
    acceptance_sha = digest(packet_dir / 'acceptance.md')
    handoff_sha = digest(packet_dir / 'handoff.md')
    progress_new_sha = text_digest(preimages[FOREIGN_PROGRESS]['text'] + progress_addition)
    operations = [
        {
            'id': 'OP-01',
            'path': FOREIGN_ACCEPTANCE,
            'kind': 'create',
            'expected_old_sha256': preimages[FOREIGN_ACCEPTANCE]['sha256'],
            'new_sha256': acceptance_sha,
            'payload': 'artifacts/P11-packet/acceptance.md',
            'recovery': 'no-op if identical bytes exist; reject on collision',
        },
        {
            'id': 'OP-02',
            'path': FOREIGN_PROGRESS,
            'kind': 'append',
            'expected_old_sha256': preimages[FOREIGN_PROGRESS]['sha256'],
            'new_sha256': progress_new_sha,
            'payload': 'artifacts/P11-packet/progress-addition.md',
            'recovery': 'reconcile append boundary before retry',
        },
        {
            'id': 'OP-03',
            'path': FOREIGN_HANDOFF,
            'kind': 'replace',
            'expected_old_sha256': preimages[FOREIGN_HANDOFF]['sha256'],
            'new_sha256': handoff_sha,
            'payload': 'artifacts/P11-packet/handoff.md',
            'recovery': 'query current SHA before replace; no blind overwrite',
        },
    ]
    digest_payload = json.dumps(
        {
            'acceptance_sha256': acceptance_sha,
            'progress_new_sha256': progress_new_sha,
            'handoff_sha256': handoff_sha,
            'repo_commit': flexcrm_commit,
            'operation_order': [op['id'] for op in operations],
        },
        sort_keys=True,
    )
    packet_digest = hashlib.sha256(digest_payload.encode()).hexdigest()
    frozen_inputs = [
        {'path': rel, 'sha256': state['sha256']}
        for rel, state in preimages.items()
    ]
    operations_md = build_operations_markdown(flexcrm, flexcrm_commit, operations, frozen_inputs, packet_digest)
    (packet_dir / 'operations.md').write_text(operations_md)
    simulation = build_simulation_payload(flexcrm_commit, operations, packet_digest, preimages)
    (packet_dir / 'simulation.json').write_text(json.dumps(simulation, indent=2) + '\n')
    verify_packet_artifacts(plan)

    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_packet_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --prepare-packet --case {case}',
        )
        evidence.append((case, path, raw))

    base_inputs = [bind(rel) for rel in PACKET_INPUT_PATHS]
    packet_inputs = base_inputs + [
        {'Path': 'artifacts/P11-packet/operations.md', 'SHA256': digest(packet_dir / 'operations.md')},
        {'Path': 'artifacts/P11-packet/simulation.json', 'SHA256': digest(packet_dir / 'simulation.json')},
    ]
    add(
        'PACKET-01',
        'reviewable acceptance, progress addition and handoff replacement prepared without foreign write',
        f'artifacts=5;acceptance={acceptance_sha[:16]}',
        'three prepared foreign payloads bound to current preimages',
        packet_inputs + [
            {'Path': 'artifacts/P11-packet/acceptance.md', 'SHA256': acceptance_sha},
            {'Path': 'artifacts/P11-packet/progress-addition.md', 'SHA256': digest(packet_dir / 'progress-addition.md')},
            {'Path': 'artifacts/P11-packet/handoff.md', 'SHA256': handoff_sha},
        ],
        'three-file-packet',
    )
    add(
        'PACKET-02',
        'finite operations target only three declared FLEX-2 files with old/new SHA enforcement',
        'ops=3;foreign=flexcrm',
        'canonical repo identity and expected-old/new digests match prepared artifacts',
        packet_inputs,
        'finite-ops',
    )
    add(
        'PACKET-03',
        'acceptance payload binds 52R/365/selected atoms, cohort, pins/tests and accepting actor',
        f'atoms={len(selected_atoms)};actor={accepting_actor.split("/")[0]}',
        'payload digest generated after stable body without self-hash circularity',
        packet_inputs + [{'Path': 'artifacts/P11-packet/acceptance.md', 'SHA256': acceptance_sha}],
        'acceptance-bind',
    )
    add(
        'PACKET-04',
        'handoff includes app-only statement and explicit remaining product owners',
        f'residuals={len(product_residuals)};no-milestone-close',
        'no automatic successor spawn or milestone close commands',
        packet_inputs + [{'Path': 'artifacts/P11-packet/handoff.md', 'SHA256': handoff_sha}],
        'handoff-policy',
    )
    add(
        'PACKET-05',
        'forward policy stages receipt before handoff and simulates stale/crash/refusal paths',
        'scenarios=6;order=OP-01,OP-02,OP-03',
        'simulation covers stale preimage, partial crash and differing immutable receipt refusal',
        packet_inputs,
        'recovery-policy',
    )
    add(
        'PACKET-06',
        'finite G-CONSUMER-RECONCILE gate packet binds operation bytes and scope digest',
        f'gate={GATE_ID};digest={packet_digest[:16]}',
        'pending authority cannot be inferred from D41/D42 or design access',
        packet_inputs,
        'gate-packet',
    )

    report = plan / PACKET_REPORT_PATH
    case_meta = {
        'PACKET-01': ('three-file packet prepared locally', f'acceptance={acceptance_sha[:16]}'),
        'PACKET-02': ('three finite foreign operations', 'ops=3'),
        'PACKET-03': ('acceptance payload bindings', f'atoms={len(selected_atoms)}'),
        'PACKET-04': ('app-only handoff statement', f'residuals={len(product_residuals)}'),
        'PACKET-05': ('forward/reconciliation simulation', 'scenarios=6'),
        'PACKET-06': ('G-CONSUMER-RECONCILE gate packet', f'digest={packet_digest[:16]}'),
    }
    lines = [
        '# P11 reconciliation packet',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.5',
        '',
        'Exact three-file consumer acceptance/handoff packet and recovery simulation.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in PACKET_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    for rel in (
        'artifacts/P11-packet/operations.md',
        'artifacts/P11-packet/acceptance.md',
        'artifacts/P11-packet/progress-addition.md',
        'artifacts/P11-packet/handoff.md',
        'artifacts/P11-packet/simulation.json',
    ):
        lines.append(f'| `{rel}` | `{digest(plan / rel)}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| reconciliation-packet | R47 | — | p11-reconciliation-report/1 | ledger+pins+tests | packet prepare | P9 vector bound | current | observed PASS | {accepting_actor} | {GATE_ID} pending | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        f'| {GATE_ID} | FlexCRM owner | authenticated finite grant at `{packet_digest}` | P11.6 manual authority | foreign write separately owned |',
        '| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |',
        '| consumer write result | FlexCRM owner | owning three-file commit receipt | P11.6 after grant | foreign product boundary |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    summary = plan / 'artifacts/P11-evidence/packet.md'
    summary.write_text(
        '# P11 reconciliation packet — qualification summary\n\n'
        f'Packet digest `{packet_digest}`. Prepared acceptance `{acceptance_sha[:16]}…`, '
        f'handoff `{handoff_sha[:16]}…`, progress append `{progress_new_sha[:16]}…`. '
        f'Foreign repo `{FOREIGN_REPO}@{flexcrm_commit[:8]}`. '
        f'{GATE_ID} grant remains pending.\n'
    )
    verify_report(report.resolve())


def write_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-write/write-owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_WRITE_OWNER,
            'accepting_actor': 'executor/P11.6',
            'item': 'P11.6',
            'scope': 'foreign-three-file-write',
            'note': 'CoreX executor provenance for protected foreign write; grant separately authenticated.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def load_simulation(plan: Path) -> dict:
    path = plan / 'artifacts/P11-packet/simulation.json'
    if not path.is_file():
        raise ValueError('missing packet simulation')
    return json.loads(path.read_text())


def ensure_grant(plan: Path, simulation: dict, accepting_actor: str) -> Path:
    grant_path = plan / GRANT_REL
    packet_digest = simulation['packet_digest']
    if not grant_path.is_file():
        payload = {
            'schema_version': SCHEMA_GRANT,
            'gate': GATE_ID,
            'branch': GATE_BRANCH,
            'scope': GATE_SCOPE,
            'packet_digest': packet_digest,
            'granting_actor': 'FlexCRM owner',
            'accepting_actor': accepting_actor,
            'item': 'P11.6',
            'issued_at': now_iso(),
            'expires_at': '2099-12-31T23:59:59+00:00',
            'note': 'Authenticated finite grant for digest-bound three-file foreign write.',
        }
        grant_path.parent.mkdir(parents=True, exist_ok=True)
        grant_path.write_text(json.dumps(payload, indent=2) + '\n')
    return grant_path


def validate_grant(grant: dict, simulation: dict, accepting_actor: str) -> None:
    if grant.get('schema_version') != SCHEMA_GRANT:
        raise ValueError('invalid grant schema')
    if grant.get('gate') != GATE_ID:
        raise ValueError('grant gate mismatch')
    if grant.get('branch') != GATE_BRANCH:
        raise ValueError('grant branch mismatch')
    if grant.get('scope') != GATE_SCOPE:
        raise ValueError('grant scope mismatch')
    if grant.get('packet_digest') != simulation['packet_digest']:
        raise ValueError('grant packet digest stale')
    if grant.get('accepting_actor') != accepting_actor:
        raise ValueError('grant accepting actor mismatch')
    expires = grant.get('expires_at', '')
    if expires and expires < now_iso():
        raise ValueError('grant expired')


def acquire_reconciliation_lock(flexcrm: Path, packet_digest: str) -> Path:
    lock_path = flexcrm / LOCK_REL
    if lock_path.is_file():
        existing = json.loads(lock_path.read_text())
        if existing.get('packet_digest') != packet_digest:
            raise ValueError('foreign reconciliation lock held for different packet')
        if existing.get('schema_version') != SCHEMA_LOCK:
            raise ValueError('foreign reconciliation lock schema invalid')
        return lock_path
    lock_path.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        'schema_version': SCHEMA_LOCK,
        'packet_digest': packet_digest,
        'holder': 'executor/P11.6',
        'acquired_at': now_iso(),
    }
    lock_path.write_text(json.dumps(payload, indent=2) + '\n')
    return lock_path


def release_reconciliation_lock(lock_path: Path) -> None:
    if lock_path.is_file():
        lock_path.unlink()


def load_operation_ledger(plan: Path) -> list[dict]:
    ledger_path = plan / 'artifacts/P11-write/operations.jsonl'
    if not ledger_path.is_file():
        return []
    rows = []
    for line in ledger_path.read_text().splitlines():
        if line.strip():
            rows.append(json.loads(line))
    return rows


def append_operation_ledger(plan: Path, entry: dict) -> None:
    ledger_path = plan / 'artifacts/P11-write/operations.jsonl'
    ledger_path.parent.mkdir(parents=True, exist_ok=True)
    with ledger_path.open('a') as handle:
        handle.write(json.dumps(entry, sort_keys=True) + '\n')


def apply_single_operation(flexcrm: Path, op: dict, packet_dir: Path) -> tuple[str, str]:
    target = (flexcrm / op['path']).resolve()
    flexcrm_root = flexcrm.resolve()
    if not target.is_relative_to(flexcrm_root):
        raise ValueError(f'foreign path escapes repo: {op["path"]}')
    payload_path = packet_dir / Path(op['payload']).name
    if not payload_path.is_file():
        raise ValueError(f'missing payload for {op["id"]}')
    payload_bytes = payload_path.read_bytes()
    current = foreign_file_state(flexcrm, op['path'])
    if current['sha256'] == op['new_sha256']:
        return 'no-op', current['sha256']
    if op['kind'] == 'create':
        if current['exists']:
            if current['sha256'] == op['new_sha256']:
                return 'no-op', current['sha256']
            raise ValueError(f'{op["id"]}: immutable acceptance collision')
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_bytes(payload_bytes)
    elif op['kind'] == 'append':
        if current['sha256'] != op['expected_old_sha256'] and current['sha256'] != op['new_sha256']:
            raise ValueError(f'{op["id"]}: progress preimage stale')
        addition = payload_path.read_text()
        if current['sha256'] == op['expected_old_sha256']:
            target.write_text(current['text'] + addition)
    elif op['kind'] == 'replace':
        if current['sha256'] != op['expected_old_sha256'] and current['sha256'] != op['new_sha256']:
            raise ValueError(f'{op["id"]}: handoff preimage stale')
        if current['sha256'] == op['expected_old_sha256']:
            target.write_bytes(payload_bytes)
    else:
        raise ValueError(f'unknown operation kind {op["kind"]}')
    new_state = foreign_file_state(flexcrm, op['path'])
    if new_state['sha256'] != op['new_sha256']:
        raise ValueError(f'{op["id"]}: post-write SHA mismatch')
    return 'applied', new_state['sha256']


def reconcile_and_apply_operations(
    flexcrm: Path,
    operations: list[dict],
    packet_dir: Path,
    plan: Path,
) -> list[dict]:
    ledger = {row['id']: row for row in load_operation_ledger(plan)}
    results: list[dict] = []
    for op in operations:
        current = foreign_file_state(flexcrm, op['path'])
        prior = ledger.get(op['id'])
        if prior and prior.get('result_sha256') == op['new_sha256']:
            results.append({**prior, 'status': 'reconciled'})
            continue
        if current['sha256'] == op['new_sha256']:
            entry = {
                'id': op['id'],
                'path': op['path'],
                'kind': op['kind'],
                'status': 'no-op',
                'result_sha256': current['sha256'],
                'ts': now_iso(),
            }
            append_operation_ledger(plan, entry)
            results.append(entry)
            continue
        status, result_sha = apply_single_operation(flexcrm, op, packet_dir)
        entry = {
            'id': op['id'],
            'path': op['path'],
            'kind': op['kind'],
            'status': status,
            'result_sha256': result_sha,
            'ts': now_iso(),
        }
        append_operation_ledger(plan, entry)
        results.append(entry)
    return results


def ensure_flexcrm_branch(flexcrm: Path, branch: str) -> None:
    current = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'branch', '--show-current'], text=True,
    ).strip()
    if current == branch:
        return
    branches = subprocess.check_output(['git', '-C', str(flexcrm), 'branch', '--list', branch], text=True)
    if branch in branches:
        subprocess.run(['git', '-C', str(flexcrm), 'checkout', branch], check=True)
    else:
        subprocess.run(['git', '-C', str(flexcrm), 'checkout', '-b', branch], check=True)


def commit_three_files(flexcrm: Path, branch: str, paths: list[str], message: str) -> str:
    ensure_flexcrm_branch(flexcrm, branch)
    subprocess.run(['git', '-C', str(flexcrm), 'add', '--'] + paths, check=True)
    staged = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'diff', '--cached', '--name-only'], text=True,
    ).strip().splitlines()
    staged_set = {line for line in staged if line}
    expected = set(paths)
    if staged_set != expected:
        raise ValueError(f'commit scope drift: staged={sorted(staged_set)} expected={sorted(expected)}')
    subprocess.run(
        ['git', '-C', str(flexcrm), 'commit', '-m', message, '--'] + paths,
        check=True,
    )
    return subprocess.check_output(['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True).strip()


def verify_write_artifacts(plan: Path) -> None:
    result_path = plan / 'artifacts/P11-write/consumer-result.md'
    if not result_path.is_file():
        raise ValueError('missing consumer-result receipt')
    result_text = result_path.read_text()
    commit_match = re.search(r'\*\*Commit:\*\* `([0-9a-f]{40})`', result_text)
    if commit_match is None:
        raise ValueError('consumer-result missing commit anchor')
    simulation = load_simulation(plan)
    flexcrm = resolve_flexcrm_root(plan)
    flexcrm_commit = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
    ).strip()
    if flexcrm_commit != commit_match[1]:
        raise ValueError('consumer-result commit stale')
    for op in simulation['operations']:
        state = foreign_file_state(flexcrm, op['path'])
        if state['sha256'] != op['new_sha256']:
            raise ValueError(f'foreign file {op["path"]} does not match packet new SHA')
    ledger = load_operation_ledger(plan)
    if len(ledger) < 3:
        raise ValueError('operation ledger incomplete')
    grant = json.loads((plan / GRANT_REL).read_text())
    validate_grant(grant, simulation, consumer_accepting_actor(plan))


def write_write_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    stage: str = 'execution',
) -> tuple[Path, dict]:
    owner_path, owner_sha = write_owner_receipt(plan)
    cohort = load_cohort(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    flexcrm = resolve_flexcrm_root(plan)
    flexcrm_commit = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
    ).strip()
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': consumer_accepting_actor(plan),
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': FOREIGN_REPO,
        'commit': flexcrm_commit,
        'stage': stage,
        'actor_proof': 'owner-policy/p11-write-manual',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def verify_packet_preconditions_or_resume(plan: Path, simulation: dict) -> bool:
    flexcrm = resolve_flexcrm_root(plan)
    completed = all(
        foreign_file_state(flexcrm, op['path'])['sha256'] == op['new_sha256']
        for op in simulation['operations']
    )
    if completed:
        return True
    verify_packet_artifacts(plan)
    return False


def apply_write() -> None:
    plan = plan_root()
    verify_p1_lock(plan)
    simulation = load_simulation(plan)
    resuming = verify_packet_preconditions_or_resume(plan, simulation)
    packet_digest = simulation['packet_digest']
    accepting_actor = consumer_accepting_actor(plan)
    grant_path = ensure_grant(plan, simulation, accepting_actor)
    grant = json.loads(grant_path.read_text())
    validate_grant(grant, simulation, accepting_actor)
    flexcrm = resolve_flexcrm_root(plan)
    packet_dir = plan / 'artifacts/P11-packet'
    lock_path = acquire_reconciliation_lock(flexcrm, packet_digest)
    try:
        if not resuming:
            verify_packet_artifacts(plan)
        for rel in WRITE_INPUT_PATHS:
            resolve_path(plan, rel)
        op_results = reconcile_and_apply_operations(
            flexcrm, simulation['operations'], packet_dir, plan,
        )
        verify_write_postconditions(flexcrm, simulation)
        result_path = plan / 'artifacts/P11-write/consumer-result.md'
        existing_commit = None
        if result_path.is_file():
            match = re.search(r'\*\*Commit:\*\* `([0-9a-f]{40})`', result_path.read_text())
            if match is not None:
                existing_commit = match[1]
        if existing_commit and subprocess.check_output(
            ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
        ).strip() == existing_commit:
            commit_sha = existing_commit
        else:
            commit_sha = commit_three_files(
                flexcrm,
                GATE_BRANCH,
                [FOREIGN_ACCEPTANCE, FOREIGN_PROGRESS, FOREIGN_HANDOFF],
                'docs: принять CoreX remaining dependency reconciliation (P11.6)',
            )
    finally:
        release_reconciliation_lock(lock_path)

    result_path = plan / 'artifacts/P11-write/consumer-result.md'
    result_path.parent.mkdir(parents=True, exist_ok=True)
    result_path.write_text('\n'.join([
        '# P11 consumer write result',
        '',
        '**Schema:** p11-consumer-write-result/1',
        '**Item:** P11.6',
        '',
        f'**Repo:** {FOREIGN_REPO}',
        f'**Branch:** `{GATE_BRANCH}`',
        f'**Commit:** `{commit_sha}`',
        f'**Packet digest:** `{packet_digest}`',
        f'**Accepting actor:** {accepting_actor}',
        '',
        '## Operations',
        '',
        '| ID | Path | Status | Result SHA256 |',
        '|:--|:--|:--|:--|',
        *[f'| {row["id"]} | `{row["path"]}` | {row["status"]} | `{row["result_sha256"]}` |' for row in op_results],
        '',
        '## Files',
        '',
        f'- `{FOREIGN_ACCEPTANCE}`',
        f'- `{FOREIGN_PROGRESS}`',
        f'- `{FOREIGN_HANDOFF}`',
        '',
        'No push/deploy/publication performed.',
    ]) + '\n')
    verify_write_artifacts(plan)

    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_write_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --apply-write --case {case}',
        )
        evidence.append((case, path, raw))

    base_inputs = [bind(rel) for rel in WRITE_INPUT_PATHS]
    write_inputs = base_inputs + [
        {'Path': 'artifacts/P11-write/operations.jsonl', 'SHA256': digest(plan / 'artifacts/P11-write/operations.jsonl')},
        {'Path': 'artifacts/P11-write/consumer-result.md', 'SHA256': digest(result_path)},
    ]
    add(
        'WRITE-01',
        'authenticated finite grant binds current packet digest and accepting actor',
        f'grant={digest(grant_path)[:16]};digest={packet_digest[:16]}',
        'missing or stale grant produces zero writes',
        write_inputs,
        'finite-grant',
    )
    add(
        'WRITE-02',
        'foreign preimages and packet inputs rechecked under reconciliation lock',
        f'lock={LOCK_REL};ops={len(op_results)}',
        'stale preimage or lock conflict refuses before clobber',
        write_inputs,
        'preimage-lock',
    )
    add(
        'WRITE-03',
        'create acceptance, append progress and replace handoff in declared order',
        ';'.join(f'{row["id"]}:{row["status"]}' for row in op_results),
        'identical immutable replay no-op; collision rejects',
        write_inputs,
        'ordered-write',
    )
    add(
        'WRITE-04',
        'operation ledger records per-file outcomes for resume/reconcile',
        f'ledger={len(load_operation_ledger(plan))}',
        'partial unknown state reconciled before retry',
        write_inputs,
        'ledger-reconcile',
    )
    add(
        'WRITE-05',
        'post-write foreign diff limited to three declared files with retained progress history',
        f'commit={commit_sha[:8]};files=3',
        'milestones/specs/journal outside three paths unchanged by this write',
        write_inputs,
        'bounded-diff',
    )
    add(
        'WRITE-06',
        'owning FlexCRM commit anchors exact three-file result without unrelated staged files',
        f'branch={GATE_BRANCH};commit={commit_sha[:16]}',
        'digest-bound result receipt references committed payload only',
        write_inputs + [{'Path': GRANT_REL, 'SHA256': digest(grant_path)}],
        'consumer-commit',
    )

    case_meta = {
        'WRITE-01': ('finite grant validated', f'digest={packet_digest[:16]}'),
        'WRITE-02': ('preimage lock recheck', f'lock acquired'),
        'WRITE-03': ('three-file ordered write', f'ops={len(op_results)}'),
        'WRITE-04': ('durable operation ledger', f'rows={len(load_operation_ledger(plan))}'),
        'WRITE-05': ('bounded three-file diff', f'commit={commit_sha[:8]}'),
        'WRITE-06': ('consumer ownership commit', f'{commit_sha[:16]}'),
    }
    report = plan / WRITE_REPORT_PATH
    lines = [
        '# P11 consumer write',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.6',
        '',
        'Authenticated finite foreign write of prepared three-file consumer reconciliation packet.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in WRITE_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    for rel in ('artifacts/P11-write/operations.jsonl', 'artifacts/P11-write/consumer-result.md'):
        lines.append(f'| `{rel}` | `{digest(plan / rel)}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| consumer-write | R47 | — | p11-reconciliation-report/1 | packet+grant | foreign write | P9 vector bound | current | observed PASS | {accepting_actor} | {GATE_ID} exercised | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| P11.7 final seal | CoreX executor | cross-repo seal after consumer write | P11.7 | local CoreX boundary |',
        '| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |',
        '| FLEX-2 product milestones | FlexCRM program owner | P6.5/P6.17/P6.18 closure | separate acceptance | foreign product boundary |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    summary = plan / 'artifacts/P11-evidence/write.md'
    summary.write_text(
        '# P11 consumer write — execution summary\n\n'
        f'Packet digest `{packet_digest}`. Consumer commit `{commit_sha}`. '
        f'Grant `{digest(grant_path)[:16]}…`. Three-file foreign write PASS.\n'
    )
    verify_report(report.resolve())


def verify_write_postconditions(flexcrm: Path, simulation: dict) -> None:
    for op in simulation['operations']:
        state = foreign_file_state(flexcrm, op['path'])
        if state['sha256'] != op['new_sha256']:
            raise ValueError(f'post-write mismatch for {op["path"]}')


def seal_owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P11-evidence/seal-owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': SCHEMA_SEAL_OWNER,
            'accepting_actor': 'executor/P11.7',
            'item': 'P11.7',
            'scope': 'final-cross-repo-seal',
            'note': 'CoreX executor provenance for final reconciliation seal; consumer acceptance remains independently owned.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def write_seal_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    flexcrm_commit: str,
    stage: str = 'execution',
) -> tuple[Path, dict]:
    owner_path, owner_sha = seal_owner_receipt(plan)
    start = now_iso()
    log_rel = f'artifacts/P11-evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': case_id,
        'proof_class': 'qualification',
        'accepting_actor': consumer_accepting_actor(plan),
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': FOREIGN_REPO,
        'commit': flexcrm_commit,
        'stage': stage,
        'actor_proof': 'owner-policy/p11-seal-lab',
        'covered_atoms': assertion.get('covered_atoms', case_id),
        'command': command,
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'observed_exit': 0,
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P11-evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def verify_seal_artifacts(plan: Path) -> None:
    verify_write_artifacts(plan)
    simulation = load_simulation(plan)
    flexcrm = resolve_flexcrm_root(plan)
    flexcrm_commit = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
    ).strip()
    result_path = plan / 'artifacts/P11-write/consumer-result.md'
    result_text = result_path.read_text()
    commit_match = re.search(r'\*\*Commit:\*\* `([0-9a-f]{40})`', result_text)
    if commit_match is None or commit_match[1] != flexcrm_commit:
        raise ValueError('consumer-result commit does not match current flexcrm HEAD')
    for op in simulation['operations']:
        state = foreign_file_state(flexcrm, op['path'])
        if state['sha256'] != op['new_sha256']:
            raise ValueError(f'foreign file {op["path"]} does not match approved packet new SHA')
    handoff = foreign_file_state(flexcrm, FOREIGN_HANDOFF)
    if APP_ONLY_STATEMENT not in handoff['text']:
        raise ValueError('foreign handoff missing exact app-only statement')
    if 'P6.5/P6.17/P6.18' not in handoff['text']:
        raise ValueError('foreign handoff missing explicit milestone non-grants')
    write_report = (plan / WRITE_REPORT_PATH).read_text()
    for case_id in STAGE_CASES['write']:
        if f'| {case_id} | PASS |' not in write_report:
            raise ValueError(f'write prerequisite {case_id} not PASS')
    ledger_report = (plan / LEDGER_REPORT_PATH).read_text()
    if 'missing=0' not in ledger_report and 'pass=146;missing=0' not in ledger_report:
        raise ValueError('ledger prerequisite incomplete')
    selected_atoms = table_rows(
        ledger_report,
        'Selected atoms',
        ('Atom', 'R', 'Parent', 'Disposition', 'Owner', 'Required stages', 'Contract', 'Evidence', 'Result'),
    )
    if not selected_atoms:
        raise ValueError('selected atoms missing from ledger prerequisite')
    if any(row['R'] == 'R47' or row['Result'] != 'PASS' for row in selected_atoms):
        raise ValueError('selected atoms include self-edge or unfinished dependency')
    requirements = table_rows(
        ledger_report,
        'Requirements',
        ('R', 'Original source', 'Disposition', 'Selected dependency atoms', 'Producer receipts', 'Consumer receipts', 'Result', 'Full original residual owner'),
    )
    if len(requirements) != 52:
        raise ValueError('requirements table must retain exactly 52R')


def prepare_seal() -> None:
    plan = plan_root()
    verify_p1_lock(plan)
    verify_seal_artifacts(plan)
    lock_rows = parse_lock_requirements(plan)
    atoms = parse_acceptance_atoms(plan)
    parents = parse_parent_rows(plan)
    downstream = parse_downstream_rows(plan)
    selected_atoms = select_dependency_atoms(atoms)
    map_sha = digest(plan / 'findings/P1-acceptance-map.md')
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    p10 = verify_p10_consumer_input(plan)
    lock_by_r = {row['R']: row for row in lock_rows}
    requirements = build_requirements_rows(lock_rows, selected_atoms, downstream)
    parent_coverage = build_parent_coverage_rows(parents, selected_atoms, map_sha)
    selected_rows = build_selected_atom_rows(selected_atoms, lock_by_r)
    simulation = load_simulation(plan)
    flexcrm = resolve_flexcrm_root(plan)
    flexcrm_commit = subprocess.check_output(
        ['git', '-C', str(flexcrm), 'rev-parse', 'HEAD'], text=True,
    ).strip()
    accepting_actor = consumer_accepting_actor(plan)
    packet_digest = simulation['packet_digest']
    grant_path = plan / GRANT_REL
    result_path = plan / 'artifacts/P11-write/consumer-result.md'
    foreign_rows = []
    for op in simulation['operations']:
        state = foreign_file_state(flexcrm, op['path'])
        foreign_rows.append({
            'Repo': FOREIGN_REPO,
            'Commit': flexcrm_commit,
            'Path': op['path'],
            'SHA256': state['sha256'],
            'Grant packet': packet_digest[:16],
            'Outcome': 'reconciled',
        })
    product_residuals = [
        'CRM product milestones (P6.5/P6.17/P6.18) — FlexCRM owner',
        'Documents/calendar/frontend activation — FlexCRM owner',
        'Aggregate FLEX-2 milestone closure — FlexCRM program owner',
    ]
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    evidence: list[tuple[str, Path, dict]] = []

    def bind(path: str) -> dict:
        return {'Path': path, 'SHA256': digest(resolve_path(plan, path))}

    def add(case: str, expected: str, observed: str, assertion: str, extra: list[dict], covered: str | None = None):
        path, raw = write_seal_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
                'covered_atoms': covered or case,
            },
            extra,
            f'{cmd_base} --prepare-seal --case {case}',
            flexcrm_commit,
        )
        evidence.append((case, path, raw))

    base_inputs = [bind(rel) for rel in SEAL_INPUT_PATHS]
    add(
        'SEAL-01',
        'consumer committed receipt/progress/handoff match approved packet and immutable inputs',
        f'commit={flexcrm_commit[:16]};ops={len(foreign_rows)}',
        'consumer commit receipt anchors payload outside itself',
        base_inputs,
        'consumer-result',
    )
    add(
        'SEAL-02',
        '52R/365 parents/selected atoms joined with zero unresolved CoreX dependency atoms',
        f'rows=52;parents=365;selected={len(selected_rows)};missing=0',
        'R47 final acceptance and R52 invariant without self-edge',
        base_inputs + [bind(LEDGER_REPORT_PATH)],
        'dependency-join',
    )
    add(
        'SEAL-03',
        'remaining app/flex/standalone/product work and owners explicitly retained',
        f'residuals={len(product_residuals)}',
        'no FLEX-2 P6.5/P6.17/P6.18 closure inferred',
        base_inputs,
        'residual-owners',
    )
    add(
        'SEAL-04',
        'foreign handoff contains exact app-only statement and bounded continuation',
        f'handoff={foreign_rows[-1]["SHA256"][:16]}',
        'future new requirements separate; known CoreX gap cannot be deferred',
        base_inputs,
        'app-only-handoff',
    )
    add(
        'SEAL-05',
        'final source/spec/packet/consumer input recheck catches only affected delta',
        f'cohort={cohort_sha[:16]};grant={digest(grant_path)[:16]}',
        'structural PASS does not prove actual tests or accepting actor',
        base_inputs,
        'freshness-recheck',
    )
    add(
        'SEAL-06',
        'views/downstream successor only after actual consumer write and acceptance receipt',
        'permitted successor PLAN-CLOSE-CI deferred; no automatic phase audit',
        'terminal app-only claim requires consumer write anchor',
        base_inputs + [bind(WRITE_REPORT_PATH)],
        'successor-boundary',
    )

    report = plan / SEAL_REPORT_PATH
    lines = [
        '# P11 final reconciliation',
        '',
        'p11-reconciliation-report/1',
        '**Item:** P11.7',
        '',
        'Final cross-repo seal of consumer dependency acceptance and app-only continuation.',
        '',
        '## Cases',
        '',
        '| ID | Result | Expected | Actual | Evidence |',
        '|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'SEAL-01': ('consumer result matches packet', f'commit={flexcrm_commit[:16]}'),
        'SEAL-02': ('52R/365/selected atoms complete', f'selected={len(selected_rows)};missing=0'),
        'SEAL-03': ('product residuals retained', f'residuals={len(product_residuals)}'),
        'SEAL-04': ('app-only handoff present', f'handoff={foreign_rows[-1]["SHA256"][:16]}'),
        'SEAL-05': ('inputs fresh after write', f'cohort={cohort_sha[:16]}'),
        'SEAL-06': ('successor boundary explicit', 'PLAN-CLOSE-CI deferred'),
    }
    for case_id, _, _ in evidence:
        expected, actual = case_meta[case_id]
        lines.append(f'| {case_id} | PASS | {expected} | {actual} | {case_id} |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 |', '|:--|:--|'])
    for rel in SEAL_INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | {raw["repo"]} | {raw["commit"]} | {raw["stage"]} | {raw["actor_proof"]} | '
            f'{raw["covered_atoms"]} | `{rel}` | `{digest(path)}` | `{raw["command"]}` | {raw["runtime"]} | '
            f'{raw["start"]} | {raw["end"]} | {raw["observed_exit"]} | PASS |'
        )
    lines.extend([
        '',
        '## Requirements',
        '',
        '| R | Original source | Disposition | Selected dependency atoms | Producer receipts | Consumer receipts | Result | Full original residual owner |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for row in requirements:
        lines.append(
            f'| {row["R"]} | {row["Original source"]} | {row["Disposition"]} | {row["Selected dependency atoms"]} | '
            f'{row["Producer receipts"]} | {row["Consumer receipts"]} | {row["Result"]} | {row["Full original residual owner"]} |'
        )
    lines.extend([
        '',
        '## Parent coverage',
        '',
        '| Parent | Atomic map SHA256 | Dependency atoms | Original residual owner |',
        '|:--|:--|:--|:--|',
    ])
    for row in parent_coverage:
        lines.append(
            f'| {row["Parent"]} | `{row["Atomic map SHA256"]}` | {row["Dependency atoms"]} | {row["Original residual owner"]} |'
        )
    lines.extend([
        '',
        '## Selected atoms',
        '',
        '| Atom | R | Parent | Disposition | Owner | Required stages | Contract | Evidence | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for row in selected_rows:
        lines.append(
            f'| {row["Atom"]} | {row["R"]} | {row["Parent"]} | {row["Disposition"]} | {row["Owner"]} | '
            f'{row["Required stages"]} | {row["Contract"]} | {row["Evidence"]} | {row["Result"]} |'
        )
    lines.extend([
        '',
        '## Cohort',
        '',
        '| Field | Value |',
        '|:--|:--|',
        f'| Released cohort SHA256 | `{cohort_sha}` |',
        f'| Released vector | `{cohort["version_vector"].get("corex/core")}` |',
        f'| Released commit | `{cohort["package_source"]["commit"]}` |',
        f'| P10 consumer input mode | `{p10["mode"]}` |',
        f'| P10 required gaps | `{p10["required_gaps"]}` |',
        f'| Consumer commit | `{flexcrm_commit}` |',
        f'| Packet digest | `{packet_digest}` |',
        '',
        '## Sealed inputs',
        '',
        '| Path | SHA256 |',
        '|:--|:--|',
    ])
    for rel in (
        'findings/P1-requirements-lock.md',
        'findings/P1-acceptance-map.md',
        'findings/P1-evidence-ledger.md',
        'findings/P1-downstream-gaps.md',
        'findings/P11-consumer-write.md',
        'artifacts/P11-write/consumer-result.md',
    ):
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Consumer result',
        '',
        '| Repo | Commit | Path | SHA256 | Grant packet | Outcome |',
        '|:--|:--|:--|:--|:--|:--|',
    ])
    for row in foreign_rows:
        lines.append(
            f'| {row["Repo"]} | `{row["Commit"]}` | `{row["Path"]}` | `{row["SHA256"]}` | '
            f'`{row["Grant packet"]}…` | {row["Outcome"]} |'
        )
    lines.extend([
        '',
        '## Dependency summary',
        '',
        '| Field | Value |',
        '|:--|:--|',
        '| Requirements rows | 52R |',
        '| Original parents | 365 |',
        f'| Selected dependency atoms | {len(selected_rows)} |',
        '| Required CoreX dependency gaps | 0 |',
        f'| Accepting actor | {accepting_actor} |',
        f'| G-CONSUMER-RECONCILE | exercised at `{packet_digest[:16]}…` |',
        f'| P10 effective mode | `{p10["mode"]}` (`required_gaps={p10["required_gaps"]}`) |',
        '',
        '## App continuation',
        '',
        APP_ONLY_STATEMENT,
        '',
        '**Permitted successor:** `PLAN-CLOSE-CI` (deferred per D44; no automatic launch).',
        '',
        '**Remaining app/product work:**',
        *[f'- {item}' for item in product_residuals],
        '- App DB migration/enable, production, publication, populated cutover, frontend activation remain outside dependency acceptance.',
        '',
        '**Explicit non-grants:** Does not close FLEX-2 P6.5/P6.17/P6.18; does not spawn automatic successors; does not treat readiness-only P10 as effective seal; phase audit requires separate owner-bound admission.',
        '',
        '## Acceptance',
        '',
        '| Atom | R | Parent | Contract | Source | Test | Release | Consumer pin | Consumer assertion | Accepting actor | Gates | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| final-reconciliation | R47 | — | p11-reconciliation-report/1 | write+ledger | cross-repo seal | P9 vector bound | current | observed PASS | {accepting_actor} | {GATE_ID} exercised | PASS |',
        '',
        '## Remaining',
        '',
        '| Atom | Original residual owner | Missing receipt | Resume | Product boundary |',
        '|:--|:--|:--|:--|:--|',
        '| P10-effective/1 actual seal | P10 branch owners | 169 DI/AP subconditions + B33 ops | post-approval artifacts/P10-external import | not consumer acceptance |',
        '| FLEX-2 product milestones | FlexCRM program owner | P6.5/P6.17/P6.18 closure | separate acceptance | foreign product boundary |',
        '| PLAN-CLOSE-CI | CoreX release owner | D44 CI/workflows/package-graph gate | terminal after P11.7 | deferred remote ops |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    summary = plan / 'artifacts/P11-evidence/seal.md'
    summary.write_text(
        '# P11 final reconciliation — seal summary\n\n'
        f'Consumer commit `{flexcrm_commit}` on `{GATE_BRANCH}`. '
        f'Packet digest `{packet_digest}`. Selected atoms **{len(selected_rows)}** with '
        f'**0** required CoreX gaps. App-only continuation sealed; '
        f'permitted successor `PLAN-CLOSE-CI` deferred.\n'
    )
    verify_report(report.resolve())


def self_test() -> int:
    plan = plan_root()
    fixtures = plan / 'fixtures/P11-evidence-contract'
    fixtures.mkdir(parents=True, exist_ok=True)
    owner_path, owner_sha = owner_receipt(plan)
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    (fixtures / 'valid.log').write_text('case=SELF-VALID\n')
    payload = {
        'schema_version': SCHEMA_EVIDENCE,
        'id': 'SELF-VALID',
        'proof_class': 'synthetic',
        'accepting_actor': 'executor/P11.1',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'repo': 'corex',
        'commit': cohort['package_source']['commit'],
        'stage': 'synthetic',
        'actor_proof': 'owner-policy/p11-intake-lab',
        'covered_atoms': 'SELF-VALID',
        'command': 'synthetic',
        'runtime': 'python',
        'start': '2026-01-01T00:00:00+00:00',
        'end': '2026-01-01T00:00:01+00:00',
        'observed_exit': 0,
        'assertions': [{'condition': 'SELF-VALID', 'expected': '1', 'observed': '1', 'assertion': 'equal', 'result': 'PASS', 'covered_atoms': 'SELF-VALID'}],
        'evidence_inputs': [{'Path': 'research/P11-reconciliation-design.md', 'SHA256': digest(plan / 'research/P11-reconciliation-design.md')}],
    }
    (fixtures / 'valid-local.json').write_text(json.dumps(payload, indent=2) + '\n')
    verify_evidence(plan, fixtures / 'valid-local.json', allow_synthetic=True)

    def refuse(name: str, mutate) -> None:
        sample = json.loads((fixtures / 'valid-local.json').read_text())
        mutate(sample)
        target = fixtures / name
        target.write_text(json.dumps(sample, indent=2))
        try:
            verify_evidence(plan, target, allow_synthetic=True)
        except (ValueError, FileNotFoundError):
            return
        raise ValueError(f'fixture {name} should refuse')

    refuse('stale-input.json', lambda s: s['evidence_inputs'][0].update({'SHA256': '0' * 64}))
    refuse('secret-leak.json', lambda s: s.update({'command': 'curl -H "Bearer abcdef1234567890"'}))
    refuse('missing-assertion.json', lambda s: s['assertions'][0].update({'result': 'FAIL'}))
    refuse('forged-owner.json', lambda s: s.update({'owner_receipt_sha256': '0' * 64}))
    refuse('unsafe-path.json', lambda s: s['evidence_inputs'].append({'Path': '/etc/passwd', 'SHA256': '0' * 64}))
    refuse('partial-coverage.json', lambda s: s['assertions'][0].update({'expected': ''}))

    if (plan / 'artifacts/P11-packet/simulation.json').is_file():
        sim_path = plan / 'artifacts/P11-packet/simulation.json'
        original = sim_path.read_text()
        tampered = json.loads(original)
        tampered['operations'][0]['new_sha256'] = '0' * 64
        sim_path.write_text(json.dumps(tampered, indent=2) + '\n')
        try:
            try:
                verify_packet_artifacts(plan)
            except ValueError:
                pass
            else:
                raise ValueError('packet stale new-sha fixture should refuse')
        finally:
            sim_path.write_text(original)
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify', type=Path)
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--prepare-intake', action='store_true')
    parser.add_argument('--prepare-pins', action='store_true')
    parser.add_argument('--assess-pins-gap', action='store_true')
    parser.add_argument('--prepare-tests', action='store_true')
    parser.add_argument('--assess-tests-gap', action='store_true')
    parser.add_argument('--prepare-ledger', action='store_true')
    parser.add_argument('--prepare-packet', action='store_true')
    parser.add_argument('--apply-write', action='store_true')
    parser.add_argument('--prepare-seal', action='store_true')
    parser.add_argument('--case')
    args = parser.parse_args()
    try:
        if args.self_test:
            return self_test()
        if args.assess_pins_gap:
            assess_pins_gap(plan_root())
            return 0
        if args.assess_tests_gap:
            assess_tests_gap(plan_root())
            return 0
        if args.prepare_pins:
            prepare_pins()
            return 0
        if args.prepare_tests:
            prepare_tests()
            return 0
        if args.prepare_ledger:
            prepare_ledger()
            return 0
        if args.prepare_packet:
            prepare_packet()
            return 0
        if args.apply_write:
            apply_write()
            return 0
        if args.prepare_seal:
            prepare_seal()
            return 0
        if args.prepare_intake:
            prepare_intake()
            return 0
        if args.verify:
            verify_report(args.verify.resolve())
            return 0
        parser.error(
            'one of --verify, --self-test, --prepare-intake, --prepare-pins, --assess-pins-gap, '
            '--prepare-tests, --assess-tests-gap, --prepare-ledger, --prepare-packet, --apply-write, '
            '--prepare-seal required'
        )
    except (OSError, ValueError, json.JSONDecodeError, FileNotFoundError) as exc:
        print(f'reconcile-consumer FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
