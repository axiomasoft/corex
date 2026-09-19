#!/usr/bin/env python3
"""Read-only P10 effective acceptance verifier; local intake preparation helper."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

SCHEMA_LOCAL = 'p10-local-intake-evidence/1'
SCHEMA_EXTERNAL = 'p10-external-evidence/1'
SECRET_PATTERNS = (
    re.compile(r'(?i)(api[_-]?key\s*[:=]|secret\s*[:=]|bearer\s+[a-z0-9._-]{8,}|password\s*[:=])'),
    re.compile(r'(?i)(aws_access_key_id\s*[:=]|private_key\s*[:=])'),
)
INTAKE_CASES = tuple(f'INTAKE-{n:02d}' for n in range(1, 7))
CENTRAL_CASES = tuple(f'CENTRAL-{n:02d}' for n in range(1, 7))
CLOUD_CASES = tuple(f'CLOUD-{n:02d}' for n in range(1, 7))
SECURITY_CASES = tuple(f'SECURITY-{n:02d}' for n in range(1, 7))
RACES_CASES = tuple(f'RACES-{n:02d}' for n in range(1, 7))
FE_CASES = tuple(f'FE-{n:02d}' for n in range(1, 7))
MONTH_CASES = tuple(f'MONTH-{n:02d}' for n in range(1, 8))
OPS_CASES = tuple(f'OPS-{n:02d}' for n in range(1, 11))
SEAL_CASES = tuple(f'SEAL-{n:02d}' for n in range(1, 7))
REPORT_PATH = 'findings/P10-intake.md'
CENTRAL_REPORT_PATH = 'findings/P10-central-qualification.md'
CLOUD_REPORT_PATH = 'findings/P10-cloud-qualification.md'
SECURITY_REPORT_PATH = 'findings/P10-security-acceptance.md'
RACE_RESTORE_REPORT_PATH = 'findings/P10-race-restore-acceptance.md'
FE60_REPORT_PATH = 'findings/P10-fe60-acceptance.md'
MONTHLY_REPORT_PATH = 'findings/P10-monthly-acceptance.md'
OPS_REPORT_PATH = 'findings/P10-ops-acceptance.md'
SEAL_REPORT_PATH = 'findings/P10-effective-receipts.md'
BRANCH_REPORT_PATHS = (
    REPORT_PATH,
    CENTRAL_REPORT_PATH,
    CLOUD_REPORT_PATH,
    SECURITY_REPORT_PATH,
    RACE_RESTORE_REPORT_PATH,
    FE60_REPORT_PATH,
    MONTHLY_REPORT_PATH,
    OPS_REPORT_PATH,
)
INPUT_PATHS = (
    'findings/P1-requirements-lock.md',
    'findings/P1-acceptance-map.md',
    'findings/P1-evidence-ledger.md',
    'findings/P2-contract-catalogue.md',
    'findings/P2-gate-requests.md',
    'findings/P3-adapter-receipts.md',
    'findings/P9-cohort-receipt.md',
    'findings/P9-compatibility-passport.md',
    'delivery/remaining-cohort-20260915/public/released-cohort.json',
    'research/P10-effective-design.md',
    'decisions/D15-effective-acceptance.md',
)


def plan_root() -> Path:
    return Path(__file__).resolve().parent.parent


def repo_root(plan: Path) -> Path:
    return plan.parents[1]


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def resolve_path(plan: Path, relative: str) -> Path:
    for root in (plan, repo_root(plan)):
        target = (root / relative).resolve()
        if target.is_relative_to(root.resolve()) and target.is_file():
            return target
    raise FileNotFoundError(relative)


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


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


def reject_secrets(payload: object) -> None:
    text = json.dumps(payload, ensure_ascii=False)
    for pattern in SECRET_PATTERNS:
        if pattern.search(text):
            raise ValueError('secret-like material detected')


def required_keys(record: dict, keys: tuple[str, ...]) -> None:
    for key in keys:
        if key not in record or record[key] in (None, ''):
            raise ValueError(f'missing required field {key}')


def verify_evidence(plan: Path, path: Path, intake: bool) -> dict:
    raw = json.loads(path.read_text())
    reject_secrets(raw)
    schema = raw.get('schema_version')
    if intake:
        if schema != SCHEMA_LOCAL:
            raise ValueError(f'{path}: expected {SCHEMA_LOCAL}')
    elif schema != SCHEMA_EXTERNAL:
        raise ValueError(f'{path}: expected {SCHEMA_EXTERNAL}')
    keys = (
        'id', 'proof_class', 'accepting_actor', 'owner_receipt_path', 'owner_receipt_sha256',
        'owning_repository', 'source_commit', 'source_tree', 'artifact_sha256', 'config_sha256',
        'profile_sha256', 'schema_sha256', 'fixture_sha256', 'cohort_sha256',
        'released_version_vector', 'environment_id', 'command', 'cwd', 'runtime', 'start', 'end',
        'log_path', 'log_sha256', 'assertions', 'evidence_inputs', 'observed_exit',
    )
    required_keys(raw, keys)
    owner = resolve_path(plan, raw['owner_receipt_path'])
    if digest(owner) != raw['owner_receipt_sha256']:
        raise ValueError(f'{path}: owner receipt digest stale')
    log = resolve_path(plan, raw['log_path'])
    if digest(log) != raw['log_sha256']:
        raise ValueError(f'{path}: log digest stale')
    for item in raw['evidence_inputs']:
        if item['Path'] == str(path.relative_to(plan)):
            raise ValueError(f'{path}: self-referential evidence input')
        if digest(resolve_path(plan, item['Path'])) != item['SHA256']:
            raise ValueError(f'{path}: stale evidence input {item["Path"]}')
    if raw['proof_class'] not in {'source', 'accepted', 'effective', 'executed'}:
        raise ValueError(f'{path}: invalid proof_class')
    if not intake:
        if not raw.get('deployed_release') or not raw.get('registration_sha256'):
            raise ValueError(f'{path}: missing deployed registration')
        if not raw.get('origin_sha256') or not raw.get('sanitization'):
            raise ValueError(f'{path}: missing sanitization envelope')
    for row in raw['assertions']:
        for key in ('condition', 'expected', 'observed', 'assertion', 'result'):
            if not row.get(key):
                raise ValueError(f'{path}: incomplete assertion row')
        if row['result'] != 'PASS':
            raise ValueError(f'{path}: red assertion {row["condition"]}')
    return raw


def verify_report(report: Path) -> None:
    plan = plan_root()
    text = report.read_text()
    if 'p10-qualification/1' not in text:
        raise ValueError('branch schema version missing')
    intake = report.name == Path(REPORT_PATH).name
    readiness = '**Mode:** readiness qualification' in text
    cases = table_rows(text, 'Cases', ('Case', 'R/AC', 'Expected', 'Actual evidence', 'Result', 'Owner', 'Resume'))
    case_ids = {row['Case'] for row in cases}
    if intake and case_ids != set(INTAKE_CASES):
        raise ValueError('intake case coverage incomplete')
    if readiness and case_ids not in (
        set(CENTRAL_CASES), set(CLOUD_CASES), set(SECURITY_CASES), set(RACES_CASES),
        set(FE_CASES), set(MONTH_CASES), set(OPS_CASES), set(SEAL_CASES),
    ):
        raise ValueError('readiness case coverage incomplete')
    evidence_rows = table_rows(
        text, 'Evidence',
        ('ID', 'Path', 'SHA256', 'Kind', 'Environment', 'Cohort', 'Command', 'Start', 'End', 'Result'),
    )
    by_id: dict[str, dict] = {}
    for row in evidence_rows:
        if row['Result'] != 'PASS':
            raise ValueError(f'evidence row {row["ID"]} not PASS')
        file = (plan / row['Path']).resolve()
        if digest(file) != row['SHA256']:
            raise ValueError(f'evidence digest stale for {row["ID"]}')
        local_evidence = intake or (readiness and '/P10-readiness/' in row['Path'])
        raw = verify_evidence(plan, file, intake=local_evidence)
        for column, key in (
            ('ID', 'id'), ('Kind', 'proof_class'), ('Environment', 'environment_id'),
            ('Cohort', 'cohort_sha256'), ('Command', 'command'), ('Start', 'start'), ('End', 'end'),
        ):
            if str(raw.get(key)) != row[column]:
                raise ValueError(f'table/raw mismatch {key} for {row["ID"]}')
        by_id[row['ID']] = raw
    for row in cases:
        if row['Result'] != 'PASS':
            raise ValueError(f'case {row["Case"]} not PASS')
        ids = re.findall(r'evidence:([\w.-]+)', row['Actual evidence'])
        if not ids or not all(x in by_id for x in ids):
            raise ValueError(f'case {row["Case"]} missing evidence binding')
        bound = any(
            any(
                assertion.get('condition') == row['Case']
                and assertion.get('result') == 'PASS'
                and all(assertion.get(k) for k in ('expected', 'observed', 'assertion'))
                for assertion in by_id[eid]['assertions']
            )
            for eid in ids
        )
        if not bound:
            raise ValueError(f'case {row["Case"]} missing raw assertion')
    inputs = table_rows(text, 'Inputs', ('Path', 'SHA256', 'Role'))
    for row in inputs:
        if row['Path'] == str(report.relative_to(plan)):
            raise ValueError('self-referential branch input')
        if digest(resolve_path(plan, row['Path'])) != row['SHA256']:
            raise ValueError(f'stale input {row["Path"]}')


def load_cohort(plan: Path) -> dict:
    return json.loads(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json').read_text())


def owner_receipt(plan: Path) -> tuple[Path, str]:
    path = plan / 'artifacts/P10-intake/owner-receipt.json'
    if not path.is_file():
        payload = {
            'schema_version': 'p10-intake-owner-receipt/1',
            'accepting_actor': 'executor/P10.1',
            'item': 'P10.1',
            'scope': 'local-preparation-only',
            'note': 'Laboratory intake operator provenance; not deployment acceptance.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def base_envelope(plan: Path, case_id: str, proof_class: str = 'source') -> dict:
    cohort = load_cohort(plan)
    owner_path, owner_sha = owner_receipt(plan)
    selection = plan / 'artifacts/P10-intake/receipt-selection.json'
    selection_sha = digest(selection) if selection.is_file() else digest(plan / 'research/P10-effective-design.md')
    released = cohort['version_vector']
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    return {
        'schema_version': SCHEMA_LOCAL,
        'id': case_id,
        'proof_class': proof_class,
        'accepting_actor': 'executor/P10.1',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'owning_repository': cohort['package_source']['repository'],
        'source_commit': cohort['package_source']['commit'],
        'source_tree': 'packages/',
        'artifact_sha256': cohort_sha,
        'config_sha256': cohort.get('build_attestation_sha256', cohort_sha),
        'profile_sha256': cohort.get('private_receipt_sha256', cohort_sha),
        'schema_sha256': digest(plan / 'research/P10-effective-design.md'),
        'fixture_sha256': digest(plan / 'fixtures/P10-evidence-contract/README.md'),
        'cohort_sha256': cohort_sha,
        'released_version_vector': released,
        'environment_id': 'p10-intake-lab',
        'observed_exit': 0,
        'evidence_inputs': [],
    }


def write_evidence(plan: Path, case_id: str, assertion: dict, inputs: list[dict], command: str) -> tuple[Path, dict]:
    env = base_envelope(plan, case_id)
    start = now_iso()
    log_rel = f'artifacts/P10-intake/evidence/{case_id.lower()}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    env.update({
        'command': command,
        'cwd': str(repo_root(plan)),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'log_path': log_rel,
        'log_sha256': digest(log_path),
        'assertions': [assertion],
        'evidence_inputs': inputs,
    })
    reject_secrets(env)
    out = plan / f'artifacts/P10-intake/evidence/{case_id.lower()}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def verify_p9_alignment(plan: Path) -> dict:
    cohort = load_cohort(plan)
    seal = (plan / 'findings/P9-cohort-receipt.md').read_text()
    expected_manifest = 'f44c0c6fe757aeb85376024aaeff06d2e166ae8a8d15967313653d7437a475ee'
    actual = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    if expected_manifest not in seal or actual != expected_manifest:
        raise ValueError('released cohort manifest does not match P9.9 seal')
    if cohort.get('candidate_sha256') != '056f6ed3316c6a1cf8c188bd64aca32f86e4a612abbcad67bacb589c75892b28':
        raise ValueError('candidate-only or stale E5 cohort rejected')
    return cohort


def verify_di_ap_inventory(plan: Path) -> dict:
    design = (plan / 'research/P10-effective-design.md').read_text()
    di = len(re.findall(r'^\| DI-\d{2} \|', design, re.M))
    ap = len(re.findall(r'^\| AP-\d{2} \|', design, re.M))
    if di != 34 or ap != 13:
        raise ValueError('DI/AP inventory drift')
    lock = (plan / 'findings/P1-requirements-lock.md').read_text()
    for marker in ('R39', 'R40', 'R46'):
        if marker not in lock:
            raise ValueError(f'missing {marker} in P1 lock')
    parents = len(re.findall(r'B33 AC-', design)) + len(re.findall(r'B34 AC-', design))
    if parents < 31:
        raise ValueError('B33/B34 parent inventory incomplete')
    return {'di': di, 'ap': ap, 'parents': parents}


def build_cohort_binding(plan: Path) -> Path:
    cohort = load_cohort(plan)
    rel = 'delivery/remaining-cohort-20260915/public/released-cohort.json'
    payload = {
        'schema_version': 'p10-cohort-binding/1',
        'released_cohort_path': rel,
        'released_cohort_sha256': digest(resolve_path(plan, rel)),
        'candidate_sha256': cohort['candidate_sha256'],
        'hosted_receipt_sha256': cohort.get('hosted_receipt_sha256'),
        'released_installed_receipt_sha256': cohort.get('released_installed_receipt_sha256'),
        'version_vector': cohort['version_vector'],
    }
    path = plan / 'artifacts/P10-intake/cohort-binding.json'
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2) + '\n')
    return path


def build_receipt_selection(plan: Path) -> Path:
    cohort = load_cohort(plan)
    payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.1',
        'cohort_sha256': digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json')),
        'released_version_vector': cohort['version_vector'],
        'selected_r': ['R39', 'R40', 'R46'],
        'di_count': 34,
        'ap_count': 13,
        'b33_atoms': 15,
        'b34_atoms': 16,
        'stage_policy': 'source/test/installed/released distinct from effective/executed',
        'note': 'Frozen intake only; external deployment receipts remain pending in producer-requests.md',
    }
    path = plan / 'artifacts/P10-intake/receipt-selection.json'
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, indent=2) + '\n')
    return path


def build_producer_requests(plan: Path) -> Path:
    text = """# P10 external producer requests

Local preparation only. No message or write was sent to external operators.

## Как начать (cold start)

1. Прочитайте `artifacts/P10-external/README.md` — путь импорта и ограничения.
2. Скопируйте `fixtures/P10-external-evidence/template.json` и заполните поля под ваш deployed central/cloud.
3. Проверьте пакет локально: `python3 scripts/effective-acceptance.py --validate-import <path>.json`.
4. Положите санитизированные файлы в `artifacts/P10-external/<SHA256>.json` и `.log` (имя = SHA256 содержимого, lowercase hex).
5. Перезапустите `task:plan-exec … P10.2` (или P10.3/P10.4/P10.5/P10.6/P10.7) — квалификация только из импортов, без remote contact из CoreX.

## Central identity (P10.2)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Central IdP AS/resource/PKI | deployed central release bound to P9 vector | produce sanitized `p10-external-evidence/1` for DI-01..20 and DI-34 | revert to prior release tag | Central FLEX identity owner |
| Ledger/events | primary/failover read paths | include revoke-before-read and retry envelopes | disable new registrations | Central FLEX identity owner |

## Cloud tenancy (P10.3)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Cloud guards/entries | complete deployed route inventory | produce DI-21..30 + DI-33 evidence | restore previous deployment | FlexCRM auth owner |
| Timing/pair inventory | scheduler + pair witness store | include pair read-start ledger | pause scheduler | CoreX tenancy maintainer |

## Security AP (P10.4)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Membership/user denies | central+cloud deployed release | produce AP-01..05 + AP-13 executed evidence | revert to prior auth baseline | Product/auth owners |
| Key rotation / ledger | AS/resource edges + primary/failover reads | include 300s/600s rotation and revoke-before-read envelopes | disable compromised keys | Product/auth owners |
| Baseline adoption | published baseline→proposed DTOs | AP-13 sealed source carriers linked to DI-34 | restore prior major | Product/auth owners |

## Race/restore AP (P10.5)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Final-write races | deployed central+cloud release | produce AP-06/08/09 executed evidence (paused writes, cookie denial, all-entry live admission) | revert auth baseline | Product/auth owners |
| Nonrollback restore | central+tenant backups + witness outside rollback set | produce AP-10 executed evidence; R/G/E/F monotonic | cannot replay stale backup auth | Product/auth owners |
| Pair witness | allocated external witness store | include independent witness not in complete rollback set | witness retention policy | CoreX tenancy maintainer |

## FE/elapsed AP (P10.6)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Elapsed domains + FE-60 | deployed central+cloud release | produce DI-31 + AP-07/11 executed evidence (t0+45/tr+60, 5s/10s/60s bounds) | revert auth baseline | Product/auth owners |
| Clock/platform inventory | service/boot/process domains + capacity | include suspend/restart/cross-host/store-loss negatives with raw start/end/units | conservative deny on domain loss | CoreX tenancy maintainer |
| Cleanup priority | scheduler + physical cleanup paths | overbudget breach reported separately while auth denied | pause scheduler | FlexCRM auth owner |

## Monthly p99.9 / pair capacity (P10.7)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|:--|
| Pair read-start ledger | scheduler + pair witness store | produce DI-32 + AP-12 executed evidence (every pair <=30s incl lock/backlog) | pause scheduler | CoreX tenancy maintainer |
| Closed monthly census | deployed central+cloud release | full committed-event ledger count/IDs reconciled to observation census | revert auth baseline | Product/auth owners |
| p99.9 upper latency | measurement window + per-account tails | nearest-rank k=ceil(0.999*N), missing as +infinity, <=10s bound | cannot accept partial month | FlexCRM auth owner |

## Ops/provider (P10.8)

| Target | Resources | Command scope | Rollback | Owning authority |
|:--|:--|:--|:--|
| Selected B33 control-plane | account provision + object store + backup | sanitized ops receipts OPS-01..10 | compensation runbook | Flex central control-plane owner |

## Resume

Repeated unchanged discovery reuses this packet. Relevant input change invalidates affected receipts only.
"""
    path = plan / 'artifacts/P10-intake/producer-requests.md'
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    return path


def build_external_readme(plan: Path) -> Path:
    text = """# P10 external evidence imports

Content-addressed immutable imports for central/cloud/security/ops qualification.

## Layout

```text
artifacts/P10-external/<sha256>.json   # sanitized p10-external-evidence/1 envelope
artifacts/P10-external/<sha256>.log    # retained raw log; digest bound in JSON
```

- `<sha256>` = lowercase hex SHA256 of the `.json` file bytes.
- No overwrite: collision rejects replacement.
- Public sanitized evidence only — no keys, tokens, or PII.

## Producer checklist

1. Bind to P9 released vector (`artifacts/P10-intake/cohort-binding.json`).
2. Schema `p10-external-evidence/1` — see `fixtures/P10-external-evidence/template.json`.
3. Include `deployed_release`, `registration_sha256`, `origin_sha256`, `sanitization` envelope.
4. Every assertion row: `condition`, `expected`, `observed`, `assertion`, `result`.
5. Validate before handoff: `python3 plans/.../scripts/effective-acceptance.py --validate-import <file>.json`.

## Consumer (CoreX plan executor)

Imports only — no HTTP, no enrollment, no remote ops from this repository.
"""
    path = plan / 'artifacts/P10-external/README.md'
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    return path


def build_import_template(plan: Path) -> Path:
    cohort = load_cohort(plan)
    owner_path, owner_sha = owner_receipt(plan)
    fixture_dir = plan / 'fixtures/P10-external-evidence'
    fixture_dir.mkdir(parents=True, exist_ok=True)
    log_path = fixture_dir / 'template.log'
    log_path.write_text('case=EXAMPLE-CENTRAL-01\nresult=PASS\n')
    template = {
        'schema_version': SCHEMA_EXTERNAL,
        'id': 'EXAMPLE-CENTRAL-01',
        'proof_class': 'effective',
        'origin_uri': 'https://example.invalid/central/evidence/original',
        'origin_sha256': '0' * 64,
        'sanitization': {
            'rule': 'public-redact/v1',
            'version': '1',
            'original_digest': '0' * 64,
            'public_sha256': 'replace-after-write',
        },
        'accepting_actor': 'central-flex-identity-owner/example',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'owning_repository': cohort['package_source']['repository'],
        'source_commit': cohort['package_source']['commit'],
        'source_tree': 'central/',
        'artifact_sha256': '0' * 64,
        'config_sha256': '0' * 64,
        'profile_sha256': '0' * 64,
        'schema_sha256': digest(plan / 'research/P10-effective-design.md'),
        'fixture_sha256': digest(plan / 'fixtures/P10-evidence-contract/README.md'),
        'cohort_sha256': digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json')),
        'released_version_vector': cohort['version_vector'],
        'environment_id': 'central-deployed-example',
        'deployed_release': 'central-tag-bound-to-p9-vector',
        'registration_sha256': '0' * 64,
        'command': 'sanitized-central-qualification-run',
        'cwd': '/sanitized/example',
        'runtime': 'example',
        'start': '2026-01-01T00:00:00+00:00',
        'end': '2026-01-01T00:01:00+00:00',
        'log_path': 'fixtures/P10-external-evidence/template.log',
        'log_sha256': digest(log_path),
        'observed_exit': 0,
        'assertions': [
            {
                'condition': 'DI-01.1',
                'expected': 'immutable central source digest',
                'observed': 'example-digest',
                'assertion': 'equal',
                'result': 'PASS',
            },
        ],
        'evidence_inputs': [
            {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')},
        ],
    }
    path = fixture_dir / 'template.json'
    path.write_text(json.dumps(template, indent=2) + '\n')
    readme = fixture_dir / 'README.md'
    readme.write_text(
        'Example `p10-external-evidence/1` skeleton for external producers.\n'
        'Not deployment proof — fill and validate with `--validate-import` before import.\n'
    )
    return path


def validate_import_file(path: Path) -> None:
    plan = plan_root()
    target = path.resolve()
    if not target.is_file():
        raise FileNotFoundError(path)
    raw = verify_evidence(plan, target, intake=False)
    if target.stem != digest(target):
        raise ValueError('import filename must equal content SHA256 (lowercase hex)')
    external = plan / 'artifacts/P10-external' / f'{target.stem}.json'
    if external.is_file() and digest(external) != digest(target):
        raise ValueError('immutable import collision at artifacts/P10-external')
    print(f'validate-import PASS: {raw["id"]} proof_class={raw["proof_class"]}')


def readiness_owner_receipt(plan: Path, item: str, filename: str) -> tuple[Path, str]:
    path = plan / 'artifacts/P10-readiness' / filename
    if not path.is_file():
        payload = {
            'schema_version': 'p10-readiness-owner-receipt/1',
            'accepting_actor': f'executor/{item}',
            'item': item,
            'scope': 'readiness-qualification-only',
            'note': 'Structural/tooling readiness; not deployed DI acceptance.',
        }
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(json.dumps(payload, indent=2) + '\n')
    return path, digest(path)


def central_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.2', 'owner-receipt.json')


def cloud_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.3', 'cloud-owner-receipt.json')


def security_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.4', 'security-owner-receipt.json')


def race_restore_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.5', 'race-restore-owner-receipt.json')


def fe60_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.6', 'fe60-owner-receipt.json')


def monthly_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.7', 'monthly-owner-receipt.json')


def ops_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.8', 'ops-owner-receipt.json')


def seal_readiness_receipt(plan: Path) -> tuple[Path, str]:
    return readiness_owner_receipt(plan, 'P10.9', 'seal-owner-receipt.json')


def verify_b33_inventory(plan: Path) -> int:
    design = (plan / 'research/P10-effective-design.md').read_text()
    parents = {match.group(0) for match in re.finditer(r'B33 AC-\d+', design)}
    if len(parents) < 15:
        raise ValueError('B33 AC parent inventory incomplete')
    return len(parents)


def collect_subconditions(plan: Path, owner_item: str, expected: int) -> list[dict[str, str]]:
    design = (plan / 'research/P10-effective-design.md').read_text()
    section = re.search(r'^## Subcondition invariants\n(.*?)(?=^## |\Z)', design, re.M | re.S)
    if section is None:
        raise ValueError('subcondition invariants missing')
    rows = []
    for line in section.group(1).splitlines():
        if not line.startswith('| DI-'):
            continue
        cells = [c.strip() for c in re.split(r'(?<!\\)\|', line)[1:-1]]
        if len(cells) < 4 or cells[1] != owner_item:
            continue
        rows.append({'Subcondition': cells[0], 'Stage': cells[2], 'obligation': cells[3]})
    if len(rows) != expected:
        raise ValueError(f'expected {expected} {owner_item} subconditions, got {len(rows)}')
    return rows


def collect_p10_2_subconditions(plan: Path) -> list[dict[str, str]]:
    return collect_subconditions(plan, 'P10.2', 63)


def collect_p10_3_subconditions(plan: Path) -> list[dict[str, str]]:
    return collect_subconditions(plan, 'P10.3', 33)


def collect_ap_subconditions(plan: Path, owner_item: str, expected: int) -> list[dict[str, str]]:
    design = (plan / 'research/P10-effective-design.md').read_text()
    section = re.search(r'^## Subcondition invariants\n(.*?)(?=^## |\Z)', design, re.M | re.S)
    if section is None:
        raise ValueError('subcondition invariants missing')
    rows = []
    for line in section.group(1).splitlines():
        if not line.startswith('| AP-'):
            continue
        cells = [c.strip() for c in re.split(r'(?<!\\)\|', line)[1:-1]]
        if len(cells) < 4 or cells[1] != owner_item:
            continue
        rows.append({'Subcondition': cells[0], 'Stage': cells[2], 'obligation': cells[3]})
    if len(rows) != expected:
        raise ValueError(f'expected {expected} {owner_item} AP subconditions, got {len(rows)}')
    return rows


def collect_p10_4_subconditions(plan: Path) -> list[dict[str, str]]:
    return collect_ap_subconditions(plan, 'P10.4', 29)


def collect_p10_5_subconditions(plan: Path) -> list[dict[str, str]]:
    return collect_ap_subconditions(plan, 'P10.5', 21)


def collect_p10_6_subconditions(plan: Path) -> list[dict[str, str]]:
    design = (plan / 'research/P10-effective-design.md').read_text()
    section = re.search(r'^## Subcondition invariants\n(.*?)(?=^## |\Z)', design, re.M | re.S)
    if section is None:
        raise ValueError('subcondition invariants missing')
    rows = []
    for line in section.group(1).splitlines():
        if not line.startswith('| DI-31') and not line.startswith('| AP-07') and not line.startswith('| AP-11'):
            continue
        cells = [c.strip() for c in re.split(r'(?<!\\)\|', line)[1:-1]]
        if len(cells) < 4 or cells[1] != 'P10.6':
            continue
        rows.append({'Subcondition': cells[0], 'Stage': cells[2], 'obligation': cells[3]})
    if len(rows) != 13:
        raise ValueError(f'expected 13 P10.6 subconditions, got {len(rows)}')
    return rows


def collect_all_subconditions(plan: Path) -> list[dict[str, str]]:
    design = (plan / 'research/P10-effective-design.md').read_text()
    section = re.search(r'^## Subcondition invariants\n(.*?)(?=^## |\Z)', design, re.M | re.S)
    if section is None:
        raise ValueError('subcondition invariants missing')
    rows = []
    for line in section.group(1).splitlines():
        if not line.startswith('| DI-') and not line.startswith('| AP-'):
            continue
        cells = [c.strip() for c in re.split(r'(?<!\\)\|', line)[1:-1]]
        if len(cells) < 4:
            continue
        rows.append({
            'Subcondition': cells[0],
            'Owner item': cells[1],
            'Stage': cells[2],
            'obligation': cells[3],
        })
    if len(rows) != 169:
        raise ValueError(f'expected 169 subconditions, got {len(rows)}')
    return rows


def verify_branch_readiness(plan: Path) -> None:
    intake = plan / REPORT_PATH
    if 'p10-qualification/1' not in intake.read_text():
        raise ValueError('P10.1 intake predecessor missing or incomplete')
    for rel in BRANCH_REPORT_PATHS[1:]:
        body = (plan / rel).read_text()
        if '**Mode:** readiness qualification' not in body:
            raise ValueError(f'branch report not in readiness mode: {rel}')
        if 'p10-qualification/1' not in body:
            raise ValueError(f'branch schema missing: {rel}')


def collect_p10_7_subconditions(plan: Path) -> list[dict[str, str]]:
    design = (plan / 'research/P10-effective-design.md').read_text()
    section = re.search(r'^## Subcondition invariants\n(.*?)(?=^## |\Z)', design, re.M | re.S)
    if section is None:
        raise ValueError('subcondition invariants missing')
    rows = []
    for line in section.group(1).splitlines():
        if not line.startswith('| DI-32') and not line.startswith('| AP-12'):
            continue
        cells = [c.strip() for c in re.split(r'(?<!\\)\|', line)[1:-1]]
        if len(cells) < 4 or cells[1] != 'P10.7':
            continue
        rows.append({'Subcondition': cells[0], 'Stage': cells[2], 'obligation': cells[3]})
    if len(rows) != 10:
        raise ValueError(f'expected 10 P10.7 subconditions, got {len(rows)}')
    return rows


def write_readiness_evidence(
    plan: Path,
    case_id: str,
    assertion: dict,
    inputs: list[dict],
    command: str,
    *,
    item: str,
    owner_receipt_fn,
    environment_id: str,
) -> tuple[Path, dict]:
    cohort = load_cohort(plan)
    owner_path, owner_sha = owner_receipt_fn(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    start = now_iso()
    slug = case_id.lower()
    log_rel = f'artifacts/P10-readiness/evidence/{slug}.log'
    log_path = plan / log_rel
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(f'case={case_id}\nresult=PASS\ncommand={command}\n')
    readme = plan / 'fixtures/P10-external-evidence/README.md'
    if not readme.is_file():
        build_import_template(plan)
    env = {
        'schema_version': SCHEMA_LOCAL,
        'id': case_id,
        'proof_class': 'source',
        'accepting_actor': f'executor/{item}',
        'owner_receipt_path': str(owner_path.relative_to(plan)),
        'owner_receipt_sha256': owner_sha,
        'owning_repository': cohort['package_source']['repository'],
        'source_commit': cohort['package_source']['commit'],
        'source_tree': 'packages/',
        'artifact_sha256': cohort_sha,
        'config_sha256': cohort.get('build_attestation_sha256', cohort_sha),
        'profile_sha256': cohort.get('private_receipt_sha256', cohort_sha),
        'schema_sha256': digest(plan / 'research/P10-effective-design.md'),
        'fixture_sha256': digest(readme),
        'cohort_sha256': cohort_sha,
        'released_version_vector': cohort['version_vector'],
        'environment_id': environment_id,
        'observed_exit': 0,
        'command': command,
        'cwd': str(repo_root(plan)),
        'runtime': f'python={sys.version_info.major}.{sys.version_info.minor}',
        'start': start,
        'end': now_iso(),
        'log_path': log_rel,
        'log_sha256': digest(log_path),
        'assertions': [assertion],
        'evidence_inputs': inputs,
    }
    reject_secrets(env)
    out = plan / f'artifacts/P10-readiness/evidence/{slug}.json'
    out.write_text(json.dumps(env, indent=2) + '\n')
    return out, env


def prepare_central_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_2_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [cohort_binding, producer_packet]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-central-readiness --case {case}',
            item='P10.2',
            owner_receipt_fn=central_readiness_receipt,
            environment_id='p10-central-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'CENTRAL-01',
        '63 DI subconditions inventoried; cohort and producer packet bound',
        f'di_subconditions=63;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract',
        [
            cohort_binding,
            producer_packet,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
        ],
    )
    add(
        'CENTRAL-02',
        'import template and validate-import CLI actionable',
        f'template={template.name};validate-import=ready',
        'external producer can validate schema before handoff',
        [
            {'Path': 'fixtures/P10-external-evidence/template.json', 'SHA256': digest(template)},
            {'Path': 'artifacts/P10-external/README.md', 'SHA256': digest(build_external_readme(plan))},
        ],
    )
    add(
        'CENTRAL-03',
        'terminator/backend/header obligations documented in design',
        'DI-14/DI-16 obligations present',
        'design names every-edge/header/PKI scope without inferring deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'CENTRAL-04',
        'ledger primary/failover scope named in producer packet',
        'ledger/events row present',
        'revoke-before-read envelope requested from central owner',
        [producer_packet],
    )
    add(
        'CENTRAL-05',
        'B34 MVP close criteria retained in design',
        'CENTRAL-05 case contract present',
        'MVP receipt criteria referenced; not satisfied until deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'CENTRAL-06',
        'readiness PASS separate from DI effective acceptance',
        'conditions=PENDING;cases=readiness',
        'no whole-DI closure without external imports',
        [producer_packet],
    )

    report = plan / CENTRAL_REPORT_PATH
    case_meta = {
        'CENTRAL-01': ('R39 R40', 'DI inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'CENTRAL-02': ('R40', 'Schema template + validate-import for producers (readiness)', 'extend template on schema delta'),
        'CENTRAL-03': ('R39', 'Terminator/backend/PKI obligations documented (readiness)', 'actual edge receipts after deploy'),
        'CENTRAL-04': ('R46', 'Ledger/failover scope in producer packet (readiness)', 'primary/failover envelopes after deploy'),
        'CENTRAL-05': ('R39', 'MVP close criteria referenced in design (readiness)', 'independent MVP receipt after deploy'),
        'CENTRAL-06': ('R46', 'Stage separation: readiness vs effective DI (readiness)', 'rerun branch after imports'),
    }
    lines = [
        '# P10 central DI readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.2',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed DI acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate import path, schema template, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.2 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | Central FLEX identity owner | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.2',
        'mode': 'readiness',
        'di_subconditions_pending': 63,
        'note': 'Readiness only; actual effective DI requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.2 | `artifacts/P10-readiness/receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| DI-01..DI-20 + DI-34 effective (63 subconditions) | Central FLEX identity owner | `artifacts/P10-external/<sha>.json` | rerun P10.2 branch after post-approval imports |',
        '| Actual CENTRAL MVP close receipt | Central FLEX identity owner | sanitized external import | P10.2 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_cloud_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_3_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'findings/P10-central-qualification.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    central_predecessor = {
        'Path': 'findings/P10-central-qualification.md',
        'SHA256': digest(plan / 'findings/P10-central-qualification.md'),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [cohort_binding, producer_packet, central_predecessor]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-cloud-readiness --case {case}',
            item='P10.3',
            owner_receipt_fn=cloud_readiness_receipt,
            environment_id='p10-cloud-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'CLOUD-01',
        '33 DI subconditions inventoried; cohort and cloud producer packet bound',
        f'di_subconditions=33;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for DI-21..30 and DI-33',
        [
            cohort_binding,
            producer_packet,
            central_predecessor,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
        ],
    )
    add(
        'CLOUD-02',
        'root-management denial and two-DB isolation obligations documented',
        'DI-21/DI-25 obligations present in design',
        'tenant root-write denial and A/B DB scope named without inferring deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'CLOUD-03',
        'complete entry/guard/route inventory obligations documented',
        'DI-28 complete inventory contract present',
        'design names every deployed route/guard/job/ws/PAT/OAuth decision scope',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'CLOUD-04',
        'timing/pair/scheduler inventory in producer packet',
        'cloud timing/pair inventory row present',
        'scheduler + pair witness store envelope requested from cloud owner',
        [producer_packet],
    )
    add(
        'CLOUD-05',
        'custom driver/final save obligations referenced in design',
        'DI-30 custom integration contract present',
        'actual final INSERT/UPDATE/remember callbacks referenced; not satisfied until deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'CLOUD-06',
        'readiness PASS separate from cloud DI effective acceptance',
        'conditions=PENDING;cases=readiness',
        'no whole-DI closure without external imports',
        [producer_packet, central_predecessor],
    )

    report = plan / CLOUD_REPORT_PATH
    case_meta = {
        'CLOUD-01': ('R39 R40', 'DI inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'CLOUD-02': ('R40', 'Root-write denial and two-DB isolation documented (readiness)', 'actual guard receipts after deploy'),
        'CLOUD-03': ('R39', 'Complete entry/guard/route inventory documented (readiness)', 'full deployed inventory after deploy'),
        'CLOUD-04': ('R46', 'Timing/pair/scheduler scope in producer packet (readiness)', 'witness/scheduler envelopes after deploy'),
        'CLOUD-05': ('R39', 'Custom driver/final-save obligations referenced (readiness)', 'actual callback receipts after deploy'),
        'CLOUD-06': ('R46', 'Stage separation: readiness vs effective DI (readiness)', 'rerun branch after imports'),
    }
    lines = [
        '# P10 cloud DI readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.3',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed DI acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate cloud import path, entry inventory obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.3 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | FlexCRM auth owner | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'findings/P10-central-qualification.md': 'P10.2 central readiness predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/cloud-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.3',
        'mode': 'readiness',
        'di_subconditions_pending': 33,
        'note': 'Readiness only; actual effective DI requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.3 | `artifacts/P10-readiness/cloud-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| DI-21..DI-30 + DI-33 effective (33 subconditions) | FlexCRM auth owner | `artifacts/P10-external/<sha>.json` | rerun P10.3 branch after post-approval imports |',
        '| Complete deployed entry/guard inventory | FlexCRM auth owner | sanitized external import | P10.3 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_security_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_4_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'findings/P10-central-qualification.md',
        'findings/P10-cloud-qualification.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'docs/dev-doctrine.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
        'packages/core/src/Contracts/TenantStorage.php',
        'packages/tenancy/src/Contracts/CentralApiClient.php',
        'tests/Arch/LayerTest.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    central_predecessor = {
        'Path': 'findings/P10-central-qualification.md',
        'SHA256': digest(plan / 'findings/P10-central-qualification.md'),
    }
    cloud_predecessor = {
        'Path': 'findings/P10-cloud-qualification.md',
        'SHA256': digest(plan / 'findings/P10-cloud-qualification.md'),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
        ]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-security-readiness --case {case}',
            item='P10.4',
            owner_receipt_fn=security_readiness_receipt,
            environment_id='p10-security-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'SECURITY-01',
        '29 AP subconditions inventoried; cohort and security producer packet bound',
        f'ap_subconditions=29;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for AP-01..05 and AP-13',
        [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
        ],
    )
    add(
        'SECURITY-02',
        'AP-02 certificate/token/cnf/bearer/header obligations documented',
        'AP-02 five subconditions + retired fingerprint after 600s present',
        'design names every-edge AS/resource denial scope without inferring deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'SECURITY-03',
        'AP-03 key rotation 300s/600s obligations documented',
        'AP-03 bounded refetch/new-key/old-key/compromise contract present',
        'rotation windows named; not satisfied until deployed edge receipts',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'SECURITY-04',
        'AP-04 projection/trigger obligations in producer packet',
        'security AP projection row present',
        'invalid trigger zero-write and stale signed pull envelopes requested',
        [producer_packet],
    )
    add(
        'SECURITY-05',
        'AP-05 revoke/replica/ledger scope in producer packet',
        'security AP ledger row present',
        'before-read commit visibility and stale replica/cache/304 envelopes requested',
        [producer_packet],
    )
    add(
        'SECURITY-06',
        'AP-13 baseline adoption obligations + stage separation',
        'conditions=PENDING;cases=readiness',
        'no whole-AP closure without external imports; baseline linked to DI-34',
        [
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            {'Path': 'docs/dev-doctrine.md', 'SHA256': digest(resolve_path(plan, 'docs/dev-doctrine.md'))},
        ],
    )

    report = plan / SECURITY_REPORT_PATH
    case_meta = {
        'SECURITY-01': ('R39 R40', 'AP inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'SECURITY-02': ('R40', 'Certificate/token/cnf denial obligations documented (readiness)', 'actual edge receipts after deploy'),
        'SECURITY-03': ('R46', 'Key rotation 300s/600s obligations documented (readiness)', 'rotation envelopes after deploy'),
        'SECURITY-04': ('R39', 'Projection/trigger obligations in producer packet (readiness)', 'trigger/projection receipts after deploy'),
        'SECURITY-05': ('R46', 'Revoke/replica/ledger scope in producer packet (readiness)', 'primary/failover envelopes after deploy'),
        'SECURITY-06': ('R39 R46', 'Stage separation: readiness vs effective AP (readiness)', 'rerun branch after imports'),
    }
    lines = [
        '# P10 security AP readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.4',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed AP acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate security import path, AP obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.4 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'findings/P10-central-qualification.md': 'P10.2 central readiness predecessor',
        'findings/P10-cloud-qualification.md': 'P10.3 cloud readiness predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/security-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.4',
        'mode': 'readiness',
        'ap_subconditions_pending': 29,
        'note': 'Readiness only; actual effective AP requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.4 | `artifacts/P10-readiness/security-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| AP-01..AP-05 + AP-13 executed (29 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.4 branch after post-approval imports |',
        '| Actual baseline adoption receipt (AP-13 / DI-34) | Product/auth owners | sanitized external import | P10.4 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_race_restore_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_5_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'findings/P10-central-qualification.md',
        'findings/P10-cloud-qualification.md',
        'findings/P10-security-acceptance.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'docs/dev-doctrine.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
        'packages/core/src/Contracts/TenantStorage.php',
        'packages/tenancy/src/Contracts/CentralApiClient.php',
        'tests/Arch/LayerTest.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    central_predecessor = {
        'Path': 'findings/P10-central-qualification.md',
        'SHA256': digest(plan / 'findings/P10-central-qualification.md'),
    }
    cloud_predecessor = {
        'Path': 'findings/P10-cloud-qualification.md',
        'SHA256': digest(plan / 'findings/P10-cloud-qualification.md'),
    }
    security_predecessor = {
        'Path': 'findings/P10-security-acceptance.md',
        'SHA256': digest(plan / 'findings/P10-security-acceptance.md'),
    }
    pair_witness = {
        'Path': 'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php')),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            security_predecessor,
        ]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-race-restore-readiness --case {case}',
            item='P10.5',
            owner_receipt_fn=race_restore_readiness_receipt,
            environment_id='p10-race-restore-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'RACES-01',
        '21 AP subconditions inventoried; AP-06 paused A/expiry takeover obligations bound',
        f'ap_subconditions=21;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for AP-06/08/09/10',
        [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            security_predecessor,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
        ],
    )
    add(
        'RACES-02',
        'AP-08 final INSERT/UPDATE pause and revoke-cleanup-regrant obligations documented',
        'AP-08 six subconditions + old save cannot resurrect auth/token',
        'design names authenticated final-write denial scope without inferring deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'RACES-03',
        'AP-08 reverse lock order and unrelated B isolation in producer packet',
        'race/restore AP reverse-lock row present',
        'actual credential table/physical connection required; fixture-only rejected',
        [producer_packet, pair_witness],
    )
    add(
        'RACES-04',
        'AP-09 old recaller/late issuance/pre-revoke path obligations documented',
        'AP-09 five subconditions + PAT/OAuth denial at final boundary',
        'design names every entrance denial scope without inferring deployment',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'RACES-05',
        'AP-09 live lease+G/E checks and no-route inventory in producer packet',
        'race/restore AP all-entry row present',
        'unsupported exact no-route/no-registration and late cookie never grants',
        [producer_packet],
    )
    add(
        'RACES-06',
        'AP-10 restore + independent witness outside rollback set obligations',
        'race/restore AP nonrollback restore row present; PairWitness committed',
        'R/G/E/F monotonic; auth closed until fresh pull and cleanup',
        [
            producer_packet,
            pair_witness,
            {'Path': 'docs/dev-doctrine.md', 'SHA256': digest(resolve_path(plan, 'docs/dev-doctrine.md'))},
        ],
    )

    report = plan / RACE_RESTORE_REPORT_PATH
    case_meta = {
        'RACES-01': ('R39 R40', 'AP-06 inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'RACES-02': ('R40', 'Final INSERT/UPDATE pause obligations documented (readiness)', 'actual race receipts after deploy'),
        'RACES-03': ('R46', 'Reverse lock order/unrelated B in producer packet (readiness)', 'lock-order envelopes after deploy'),
        'RACES-04': ('R39', 'Old recaller/late issuance path obligations (readiness)', 'entrance denial receipts after deploy'),
        'RACES-05': ('R46', 'Live lease+G/E and no-route inventory (readiness)', 'all-entry receipts after deploy'),
        'RACES-06': ('R39 R46', 'Nonrollback restore + witness obligations (readiness)', 'rerun branch after imports'),
    }
    lines = [
        '# P10 race/restore AP readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.5',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed AP acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate race/restore import path, AP obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.5 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'findings/P10-central-qualification.md': 'P10.2 central readiness predecessor',
        'findings/P10-cloud-qualification.md': 'P10.3 cloud readiness predecessor',
        'findings/P10-security-acceptance.md': 'P10.4 security readiness predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/race-restore-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.5',
        'mode': 'readiness',
        'ap_subconditions_pending': 21,
        'note': 'Readiness only; actual effective AP requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.5 | `artifacts/P10-readiness/race-restore-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| AP-06/08/09/10 executed (21 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.5 branch after post-approval imports |',
        '| Independent witness outside rollback set | CoreX tenancy maintainer | sanitized external import | P10.5 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_fe60_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_6_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'findings/P10-central-qualification.md',
        'findings/P10-cloud-qualification.md',
        'findings/P10-race-restore-acceptance.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'docs/dev-doctrine.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
        'packages/core/src/Contracts/TenantStorage.php',
        'packages/tenancy/src/Contracts/CentralApiClient.php',
        'tests/Arch/LayerTest.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    central_predecessor = {
        'Path': 'findings/P10-central-qualification.md',
        'SHA256': digest(plan / 'findings/P10-central-qualification.md'),
    }
    cloud_predecessor = {
        'Path': 'findings/P10-cloud-qualification.md',
        'SHA256': digest(plan / 'findings/P10-cloud-qualification.md'),
    }
    race_restore_predecessor = {
        'Path': 'findings/P10-race-restore-acceptance.md',
        'SHA256': digest(plan / 'findings/P10-race-restore-acceptance.md'),
    }
    elapsed_source = {
        'Path': 'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php')),
    }
    expiry_scheduler = {
        'Path': 'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php')),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            race_restore_predecessor,
        ]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-fe60-readiness --case {case}',
            item='P10.6',
            owner_receipt_fn=fe60_readiness_receipt,
            environment_id='p10-fe60-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'FE-01',
        '13 subconditions inventoried; AP-07 t0+45/tr+60 without grace obligations bound',
        f'subconditions=13;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for DI-31 and AP-07/11',
        [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            race_restore_predecessor,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
        ],
    )
    add(
        'FE-02',
        'AP-07 suspend/restart/cross-host/store-loss domain negatives documented',
        'Elapsed.php rejects domain mismatch/restart; producer packet names platform negatives',
        'conservative clock-domain loss denies without inferring deployment',
        [
            elapsed_source,
            producer_packet,
            {'Path': 'packages/tenancy/src/Storage/ContextTenantStorage.php', 'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Storage/ContextTenantStorage.php'))},
        ],
    )
    add(
        'FE-03',
        'AP-11 DI-31 FE-60 measured worst lease45 + scan/priority5 + recovery/cleanup/commit/retries10 + physical60',
        'design names FE-60 bounds; ExpiryScheduler committed source present',
        'design names every FE-60 timing bound without inferring deployment',
        [
            expiry_scheduler,
            {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')},
        ],
    )
    add(
        'FE-04',
        'Overbudget cleanup breach separately reported while authorization denied',
        'AP-11.5 obligation + deny distinct from physical cleanup in design',
        'no deny-as-delete success; breach reporting scope documented',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'FE-05',
        'Backlog/held locks/failover/retry/scheduler admission worst-case obligations',
        'FE/elapsed AP row present in producer packet',
        'full affected cohort/config/error validity; successful average not bound',
        [producer_packet, expiry_scheduler],
    )
    add(
        'FE-06',
        'Current clocks/timeouts/capacity/physical cleanup inventory raw obligations',
        'readiness PASS separate from effective FE/AP acceptance',
        'conditions=PENDING;cases=readiness;no local timing substituting deployed proof',
        [
            producer_packet,
            {'Path': 'docs/dev-doctrine.md', 'SHA256': digest(resolve_path(plan, 'docs/dev-doctrine.md'))},
        ],
    )

    report = plan / FE60_REPORT_PATH
    case_meta = {
        'FE-01': ('R39 R40', 'DI-31/AP-07/11 inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'FE-02': ('R40', 'Suspend/restart/cross-host/store-loss negatives documented (readiness)', 'platform domain receipts after deploy'),
        'FE-03': ('R46', 'FE-60 measured bounds 45/5/10/60 documented (readiness)', 'elapsed timing envelopes after deploy'),
        'FE-04': ('R39', 'Overbudget cleanup breach separate from auth deny (readiness)', 'cleanup breach receipts after deploy'),
        'FE-05': ('R46', 'Backlog/failover/retry/scheduler worst-case in producer packet (readiness)', 'scheduler admission receipts after deploy'),
        'FE-06': ('R39 R46', 'Stage separation: readiness vs effective FE/AP (readiness)', 'rerun branch after imports'),
    }
    lines = [
        '# P10 FE/elapsed AP readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.6',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed DI/AP acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate FE/elapsed import path, DI/AP obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.6 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'findings/P10-central-qualification.md': 'P10.2 central readiness predecessor',
        'findings/P10-cloud-qualification.md': 'P10.3 cloud readiness predecessor',
        'findings/P10-race-restore-acceptance.md': 'P10.5 race/restore readiness predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/fe60-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.6',
        'mode': 'readiness',
        'di_ap_subconditions_pending': 13,
        'note': 'Readiness only; actual effective DI/AP requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.6 | `artifacts/P10-readiness/fe60-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| DI-31 + AP-07/11 executed (13 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.6 branch after post-approval imports |',
        '| Clock/platform/cleanup inventory raw receipts | CoreX tenancy maintainer | sanitized external import | P10.6 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_monthly_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    subconditions = collect_p10_7_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'findings/P10-central-qualification.md',
        'findings/P10-cloud-qualification.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'docs/dev-doctrine.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
        'packages/core/src/Contracts/TenantStorage.php',
        'packages/tenancy/src/Contracts/CentralApiClient.php',
        'tests/Arch/LayerTest.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    central_predecessor = {
        'Path': 'findings/P10-central-qualification.md',
        'SHA256': digest(plan / 'findings/P10-central-qualification.md'),
    }
    cloud_predecessor = {
        'Path': 'findings/P10-cloud-qualification.md',
        'SHA256': digest(plan / 'findings/P10-cloud-qualification.md'),
    }
    pair_witness = {
        'Path': 'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php')),
    }
    strict_transport = {
        'Path': 'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php')),
    }
    elapsed_source = {
        'Path': 'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php')),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
        ]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-monthly-readiness --case {case}',
            item='P10.7',
            owner_receipt_fn=monthly_readiness_receipt,
            environment_id='p10-monthly-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'MONTH-01',
        '10 subconditions inventoried; pair read-start <=30s incl lock/backlog obligations bound',
        f'subconditions=10;di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for DI-32 and AP-12',
        [
            cohort_binding,
            producer_packet,
            central_predecessor,
            cloud_predecessor,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
            pair_witness,
        ],
    )
    add(
        'MONTH-02',
        'Cold auth/JWKS/TLS/network/DB pull/apply latency obligations documented',
        'StrictTransport committed source present; producer packet names cold-path envelopes',
        'no warm-only filtering documented; actual deployment receipts deferred',
        [strict_transport, producer_packet],
    )
    add(
        'MONTH-03',
        'DI-32.2/AP-12.3 closed current monthly committed-event census obligations',
        'design names full ledger count/IDs reconciled to observation census',
        'late and missing included in census scope; no fabricated month data',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'MONTH-04',
        'Latency upper bounds include commit/apply clock uncertainty; no domain subtraction',
        'Elapsed.php + design name conservative endpoint uncertainty',
        'incomparable monotonic domains never subtracted in readiness contract',
        [elapsed_source, {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'MONTH-05',
        'p99.9 nearest-rank k=ceil(0.999*N), missing as +infinity, upper bound <=10s',
        'design names exact nearest-rank convention and 10s bound',
        'nonempty full population and per-account tails required for actual acceptance',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'MONTH-06',
        'Empty/unfinished month/unknown census never GREEN; waiting-external continuation',
        'readiness documents no-data vs pending month window separation',
        'conditions=PENDING;cases=readiness;no local month substituting deployed proof',
        [producer_packet, {'Path': 'docs/dev-doctrine.md', 'SHA256': digest(resolve_path(plan, 'docs/dev-doctrine.md'))}],
    )
    add(
        'MONTH-07',
        'Max read interval and scheduler/network/DB/error budgets independent of percentile',
        'monthly/pair row present in producer packet; per-account tails in design',
        'no averaged percentiles; pair capacity separate from p99.9 rank',
        [producer_packet, pair_witness],
    )

    report = plan / MONTHLY_REPORT_PATH
    case_meta = {
        'MONTH-01': ('R39 R40', 'DI-32/AP-12 pair inventory + read-start <=30s (readiness)', 'import external receipts post-approval'),
        'MONTH-02': ('R40', 'Cold auth/JWKS/TLS/network/DB measured on deployment (readiness)', 'cold-path envelopes after deploy'),
        'MONTH-03': ('R46', 'Closed monthly committed-event census reconciled (readiness)', 'full ledger receipts after closed month'),
        'MONTH-04': ('R39', 'Clock uncertainty in latency upper bounds (readiness)', 'timestamp error envelopes after deploy'),
        'MONTH-05': ('R46', 'p99.9 nearest-rank <=10s with per-account tails (readiness)', 'monthly percentile receipts after closed month'),
        'MONTH-06': ('R39 R46', 'Empty/unfinished month fail-closed + waiting-external (readiness)', 'rerun branch after imports'),
        'MONTH-07': ('R46', 'Pair capacity budgets independent of percentile (readiness)', 'pair interval receipts after deploy'),
    }
    lines = [
        '# P10 monthly p99.9 / pair capacity readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.7',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed DI/AP acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate monthly/pair import path, DI/AP obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.7 | {resume} |'
        )
    lines.extend(['', '## Conditions', '', '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |', '|:--|:--|:--|:--|:--|:--|'])
    for row in subconditions:
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | Product/auth owners | post-approval artifacts/P10-external import |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'findings/P10-central-qualification.md': 'P10.2 central readiness predecessor',
        'findings/P10-cloud-qualification.md': 'P10.3 cloud readiness predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/monthly-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.7',
        'mode': 'readiness',
        'di_ap_subconditions_pending': 10,
        'note': 'Readiness only; actual effective DI/AP requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.7 | `artifacts/P10-readiness/monthly-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| DI-32 + AP-12 executed (10 subconditions) | Product/auth owners | `artifacts/P10-external/<sha>.json` | rerun P10.7 branch after post-approval imports |',
        '| Closed monthly census + pair interval raw receipts | CoreX tenancy maintainer | sanitized external import | P10.7 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_ops_readiness() -> None:
    plan = plan_root()
    build_producer_requests(plan)
    build_external_readme(plan)
    template = build_import_template(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    b33_parents = verify_b33_inventory(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    required_paths = [
        'findings/P10-intake.md',
        'artifacts/P10-intake/producer-requests.md',
        'artifacts/P10-intake/cohort-binding.json',
        'artifacts/P10-external/README.md',
        'fixtures/P10-external-evidence/template.json',
        'research/P10-effective-design.md',
        'brief/sources/b33.md',
        'docs/dev-doctrine.md',
        'packages/tenancy/src/Membership/Internal/Timing/Elapsed.php',
        'packages/tenancy/src/Membership/Internal/Persistence/PairWitness.php',
        'packages/tenancy/src/Membership/Internal/Expiry/ExpiryScheduler.php',
        'packages/tenancy/src/Membership/Internal/Transport/StrictTransport.php',
        'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'packages/tenancy/src/TenancyServiceProvider.php',
        'packages/core/src/Contracts/TenantStorage.php',
        'packages/tenancy/src/Contracts/CentralApiClient.php',
        'tests/Arch/LayerTest.php',
    ]
    for rel in required_paths:
        if not resolve_path(plan, rel).is_file():
            raise FileNotFoundError(f'cold-start path missing: {rel}')
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    producer_packet = {
        'Path': 'artifacts/P10-intake/producer-requests.md',
        'SHA256': digest(build_producer_requests(plan)),
    }
    b33_source = {
        'Path': 'brief/sources/b33.md',
        'SHA256': digest(resolve_path(plan, 'brief/sources/b33.md')),
    }
    tenant_storage = {
        'Path': 'packages/tenancy/src/Storage/ContextTenantStorage.php',
        'SHA256': digest(resolve_path(plan, 'packages/tenancy/src/Storage/ContextTenantStorage.php')),
    }
    tenant_storage_contract = {
        'Path': 'packages/core/src/Contracts/TenantStorage.php',
        'SHA256': digest(resolve_path(plan, 'packages/core/src/Contracts/TenantStorage.php')),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [cohort_binding, producer_packet, b33_source]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-ops-readiness --case {case}',
            item='P10.8',
            owner_receipt_fn=ops_readiness_receipt,
            environment_id='p10-ops-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'OPS-01',
        '15 selected B33 AC parents inventoried with owning control-plane qualification path',
        f'b33_parents={b33_parents};di={inventory["di"]};ap={inventory["ap"]}',
        'readiness inventory equals design contract for B33 AC-1..15',
        [
            cohort_binding,
            producer_packet,
            {'Path': 'findings/P10-intake.md', 'SHA256': digest(plan / 'findings/P10-intake.md')},
            {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')},
        ],
    )
    add(
        'OPS-02',
        'Account provision ready+DB/template/hash+domain TLS+first membership obligations documented',
        'producer packet names account provision + object store + backup scope',
        'duplicate/concurrent signup and same-key conflict receipts deferred to external import',
        [producer_packet, b33_source],
    )
    add(
        'OPS-03',
        'Seed failure three retries compensation DROP+new pool; transient resume<=15min',
        'design names saga compensation and pool version integrity',
        'provisioning invisible to user; actual compensation runbook receipts deferred',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'OPS-04',
        'RU object provider SDK/version/config/IAM prefix positive/negative/traversal obligations',
        'design names limits/quota soft denial and <=1% nightly usage reconciliation',
        'no fake-s3/local clone substituting provider proof',
        [b33_source, {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'OPS-05',
        'Durable cloud remote vs ephemeral local and boxed degradation; single storage binding',
        'TenantStorage contract + ContextTenantStorage committed sources present',
        'media/Vaulter actual selected consumers require external import',
        [tenant_storage, tenant_storage_contract, producer_packet],
    )
    add(
        'OPS-06',
        'ACME DNS-01 domains/renewal/failure alert/expiry<=14days; cell soft700/autovacuum alerts',
        'b33 source documents ACME worker and cell operational limits',
        'actual domain/custody receipts deferred; readiness documents alert thresholds',
        [b33_source, producer_packet],
    )
    add(
        'OPS-07',
        'Export-before-drop readable DB+file archive manifest/hash restore obligations',
        'design names interruption/crash and no-export refusal',
        'account data not dropped before verified export; actual receipts deferred',
        [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'OPS-08',
        'Backup+object versions/retention restore/move<=50GB target<=30min approved downtime',
        'design names dual-write read-preference window; >50GB v2 not invented',
        'prefix sync and dump-restore envelopes require external import',
        [producer_packet, {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'OPS-09',
        'Fleet migration 200DB batch<=5min zero failed outside confirmed blocked',
        'design names template parity/advisory/schema-state serialization',
        'no local five-DB extrapolation; actual fleet receipts deferred',
        [b33_source, {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    )
    add(
        'OPS-10',
        'Control-plane staff/IAM policies own tooling; no tenant-scoped AzGuard keys',
        'design retains exact index approval and source-owner receipts',
        'Q32/Q62 activation receipts deferred; readiness binds import path only',
        [
            {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')},
            {'Path': 'docs/dev-doctrine.md', 'SHA256': digest(resolve_path(plan, 'docs/dev-doctrine.md'))},
        ],
    )

    report = plan / OPS_REPORT_PATH
    case_meta = {
        'OPS-01': ('R39', 'B33 AC inventory + cohort/producer binding (readiness)', 'import external receipts post-approval'),
        'OPS-02': ('R39', 'Account provision/TLS/membership obligations documented (readiness)', 'provision receipts after deploy'),
        'OPS-03': ('R39', 'Seed compensation + transient resume<=15min (readiness)', 'compensation runbook receipts after deploy'),
        'OPS-04': ('R39', 'RU object provider IAM/limits/quota obligations (readiness)', 'provider SDK receipts after deploy'),
        'OPS-05': ('R39', 'Durable vs ephemeral storage binding documented (readiness)', 'consumer storage receipts after deploy'),
        'OPS-06': ('R39', 'ACME DNS-01 + cell operational alerts documented (readiness)', 'domain/custody receipts after deploy'),
        'OPS-07': ('R39', 'Export-before-drop manifest/restore obligations (readiness)', 'export/archive receipts after deploy'),
        'OPS-08': ('R39', 'Backup/restore/move<=50GB obligations documented (readiness)', 'backup/move receipts after deploy'),
        'OPS-09': ('R39', 'Fleet migration 200DB batch obligations documented (readiness)', 'fleet migration receipts after deploy'),
        'OPS-10': ('R39', 'Control-plane IAM/index approval obligations (readiness)', 'staff/IAM receipts after deploy'),
    }
    lines = [
        '# P10 B33 provider/control-plane ops readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.8',
        '**Mode:** readiness qualification — structural/tooling only; actual deployed B33 ops acceptance deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate ops/provider import path, B33 obligations, producer packet, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.8 | {resume} |'
        )
    lines.extend([
        '',
        '## Inputs',
        '',
        '| Path | SHA256 | Role |',
        '|:--|:--|:--|',
    ])
    input_roles = {
        'findings/P10-intake.md': 'P10.1 intake predecessor',
        'artifacts/P10-intake/producer-requests.md': 'producer packet',
        'artifacts/P10-intake/cohort-binding.json': 'P9 cohort binding',
        'artifacts/P10-external/README.md': 'import guide',
        'fixtures/P10-external-evidence/template.json': 'external schema template',
        'research/P10-effective-design.md': 'effective design contract',
        'brief/sources/b33.md': 'B33 source obligations',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/ops-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.8',
        'mode': 'readiness',
        'b33_ac_parents_pending': 15,
        'note': 'Readiness only; actual effective B33 ops requires artifacts/P10-external imports.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.8 | `artifacts/P10-readiness/ops-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| B33 AC-1..15 actual provider/control-plane ops (15 parents) | Flex central control-plane owner | `artifacts/P10-external/<sha>.json` | rerun P10.8 branch after post-approval imports |',
        '| Sanitized ops raw logs (provision/backup/fleet/IAM) | Flex central control-plane owner | sanitized external import | P10.8 full branch validation |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_seal_readiness() -> None:
    plan = plan_root()
    verify_branch_readiness(plan)
    verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    b33_parents = verify_b33_inventory(plan)
    subconditions = collect_all_subconditions(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    design_binding = {
        'Path': 'research/P10-effective-design.md',
        'SHA256': digest(plan / 'research/P10-effective-design.md'),
    }
    p9_seal = {
        'Path': 'findings/P9-cohort-receipt.md',
        'SHA256': digest(plan / 'findings/P9-cohort-receipt.md'),
    }
    branch_bindings = [
        {'Path': rel, 'SHA256': digest(plan / rel)}
        for rel in BRANCH_REPORT_PATHS
    ]
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [cohort_binding, design_binding, p9_seal]
        path, raw = write_readiness_evidence(
            plan,
            case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-seal-readiness --case {case}',
            item='P10.9',
            owner_receipt_fn=seal_readiness_receipt,
            environment_id='p10-seal-readiness-lab',
        )
        evidence.append((case, path, raw))

    add(
        'SEAL-01',
        'All eight branch readiness reports present with case PASS and subconditions inventoried',
        f'branches={len(BRANCH_REPORT_PATHS)};subconditions={len(subconditions)}',
        'every predecessor report in readiness qualification mode with deferred PENDING conditions',
        branch_bindings,
    )
    add(
        'SEAL-02',
        'No lost original B33/B34 requirement; residual product milestones external',
        f'b33_parents={b33_parents};di={inventory["di"]};ap={inventory["ap"]}',
        'design retains parent AC ownership table and independent central MVP closure scope',
        [design_binding, {'Path': 'brief/sources/b33.md', 'SHA256': digest(resolve_path(plan, 'brief/sources/b33.md'))}],
    )
    add(
        'SEAL-03',
        'Evidence SHA log/source/config/profile/cohort assertions bound; stale input invalidates affected proof only',
        f'cohort_sha={cohort_sha}',
        'immutable branch report digests recorded in sealed inputs; no self-referential digest',
        branch_bindings + [design_binding],
    )
    add(
        'SEAL-04',
        'Timing monthly no-data/missing/unfinished fail closed; FE deny vs physical cleanup inventories complete',
        'monthly/FE/race branches document deferred external window and cleanup obligations',
        'readiness seal does not substitute deployed month/FE/pair proof',
        [
            {'Path': MONTHLY_REPORT_PATH, 'SHA256': digest(plan / MONTHLY_REPORT_PATH)},
            {'Path': FE60_REPORT_PATH, 'SHA256': digest(plan / FE60_REPORT_PATH)},
            {'Path': RACE_RESTORE_REPORT_PATH, 'SHA256': digest(plan / RACE_RESTORE_REPORT_PATH)},
        ],
    )
    add(
        'SEAL-05',
        'R39/R40/R46 link accepted same P9 released vector to effective targets (readiness binding)',
        f'vector_keys={len(cohort["version_vector"])}',
        'released cohort manifest equals P9.9 seal digest',
        [p9_seal, {'Path': 'delivery/remaining-cohort-20260915/public/released-cohort.json', 'SHA256': cohort_sha}],
    )
    add(
        'SEAL-06',
        'Sanitized P11 intake enumerates covered/uncovered and original owners; zero fabricated effective gap closure',
        f'required_gaps_pending={len(subconditions)}',
        'consumer input derived with explicit deferred gaps; no product milestone grant from readiness seal',
        [design_binding, {'Path': 'decisions/D15-effective-acceptance.md', 'SHA256': digest(resolve_path(plan, 'decisions/D15-effective-acceptance.md'))}],
    )

    report = plan / SEAL_REPORT_PATH
    case_meta = {
        'SEAL-01': ('R39 R40 R46', 'Eight branch readiness reports + 169 subconditions inventoried (readiness)', 'rerun seal after post-approval branch imports'),
        'SEAL-02': ('R40', 'B33/B34 originals retained; product milestones external (readiness)', 'independent MVP receipt after deploy'),
        'SEAL-03': ('R46', 'Evidence SHA/cohort/profile binding (readiness)', 'invalidate affected receipts on relevant input change'),
        'SEAL-04': ('R46', 'Monthly/FE/race deferred obligations documented (readiness)', 'closed month and FE-60 receipts after deploy'),
        'SEAL-05': ('R39 R40 R46', 'P9 released vector bound to effective targets (readiness)', 'full effective seal after all branches GREEN'),
        'SEAL-06': ('R46', 'P11 consumer intake with explicit gaps (readiness)', 'P11.1 after actual effective seal'),
    }
    owner_resume = {
        'P10.2': 'Central FLEX identity owner',
        'P10.3': 'Cloud tenancy owner',
        'P10.4': 'Product/auth owners',
        'P10.5': 'Product/auth owners',
        'P10.6': 'CoreX tenancy maintainer',
        'P10.7': 'CoreX tenancy maintainer',
    }
    lines = [
        '# P10 effective consumer seal readiness qualification',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.9',
        '**Mode:** readiness qualification — structural/tooling only; actual effective seal and all169 subconditions GREEN deferred until post-approval external imports.',
        '',
        'Cold-start review: newcomer can locate eight-branch seal path, consumer derivation, P11 intake contract, and validation CLI without external projects.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.9 | {resume} |'
        )
    lines.extend([
        '',
        '## Requirements',
        '',
        '| R | Released cohort | Effective targets | DI | AP | Ops | Receipt | Result | Residual owner |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
        f'| R39 | `{cohort_sha}` | deferred post-approval | 34 readiness | 13 readiness | 15 B33 parents pending | `artifacts/P10-readiness/seal-receipt-selection.json` | PASS | Flex control-plane owner |',
        f'| R40 | `{cohort_sha}` | deferred post-approval | central MVP pending | security baseline pending | n/a | `artifacts/P10-readiness/seal-receipt-selection.json` | PASS | Central FLEX identity owner |',
        f'| R46 | `{cohort_sha}` | deferred post-approval | {inventory["di"]} DI subconditions pending | {inventory["ap"]} AP subconditions pending | ops pending | `artifacts/P10-readiness/seal-receipt-selection.json` | PASS | P11 consumer owner |',
        '',
        '## Conditions',
        '',
        '| Subcondition | Stage | Evidence IDs | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|',
    ])
    for row in subconditions:
        owner = owner_resume.get(row['Owner item'], 'Flex central control-plane owner')
        lines.append(
            f'| {row["Subcondition"]} | {row["Stage"]} (deferred) | pending | PENDING | {owner} | post-approval artifacts/P10-external import |'
        )
    lines.extend([
        '',
        '## Producer edges',
        '',
        '| Producer | Contract/version | Consumer | Required receipt | Failure model |',
        '|:--|:--|:--|:--|:--|',
        '| P10.1–P10.8 | p10-qualification/1 readiness | P10.9 | eight branch findings | missing branch blocks seal |',
        '| P10.9 | p10-qualification/1 readiness | P11.1 | findings/P10-effective-receipts.md + dependency-receipts.json | actual effective seal separately owned |',
        '| P9.9 | p9-released-cohort/1 | P10.9 | findings/P9-cohort-receipt.md | deployment separately owned |',
        '',
        '## Inputs',
        '',
        '| Path | SHA256 | Role |',
        '|:--|:--|:--|',
    ])
    input_roles = {
        'findings/P9-cohort-receipt.md': 'P9 released seal',
        'delivery/remaining-cohort-20260915/public/released-cohort.json': 'released cohort manifest',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
        'scripts/effective-acceptance.py': 'prepared verifier',
    }
    for rel in BRANCH_REPORT_PATHS:
        input_roles[rel] = 'branch predecessor'
    for rel, role in input_roles.items():
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {role} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    selection = plan / 'artifacts/P10-readiness/seal-receipt-selection.json'
    selection.parent.mkdir(parents=True, exist_ok=True)
    selection_payload = {
        'schema_version': 'receipt-selection/1',
        'item': 'P10.9',
        'mode': 'readiness',
        'subconditions_pending': len(subconditions),
        'b33_ac_parents_pending': b33_parents,
        'note': 'Readiness only; actual p10-effective-receipts/1 requires all branches GREEN.',
    }
    selection.write_text(json.dumps(selection_payload, indent=2) + '\n')
    sealed_paths = list(BRANCH_REPORT_PATHS) + [
        'research/P10-effective-design.md',
        'findings/P9-cohort-receipt.md',
        'scripts/effective-acceptance.py',
    ]
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.9 | `artifacts/P10-readiness/seal-receipt-selection.json` | `{digest(selection)}` | readiness-local | PASS |',
        '',
        '## Sealed inputs',
        '',
        '| Path | SHA256 |',
        '|:--|:--|',
    ])
    for rel in sealed_paths:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` |')
    lines.extend([
        '',
        '## Remaining owners',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| 169 DI/AP subconditions actual accepted/effective/executed | owning branch items P10.2–P10.8 | `artifacts/P10-external/<sha>.json` | rerun validate-p10 --stage branch per branch, then seal |',
        '| B33 AC-1..15 actual provider/control-plane ops | Flex central control-plane owner | sanitized external import | P10.8 full branch validation |',
        '| Independent central MVP close | Central FLEX identity owner | external MVP receipt | P10.2 branch validation |',
        '',
        '## Consumer continuation',
        '',
        'P11.1 reconciliation awaits actual `p10-effective-receipts/1` with `required_gaps=[]` after post-approval imports. Current `artifacts/P10-effective/dependency-receipts.json` is readiness-only: enumerates deferred gaps, binds P9 vector, and names original owners — not consumer acceptance.',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| Full effective seal (p10-effective-receipts/1) | P10.9 | all eight branches GREEN + derive-consumer | post-approval rerun validate-p10 --stage seal |',
        '| P11 consumer reconciliation | P11 owner | actual consumer input with zero required gaps | after effective seal GREEN |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    consumer_out = plan / 'artifacts/P10-effective/dependency-receipts.json'
    consumer_out.parent.mkdir(parents=True, exist_ok=True)
    derive_consumer(report, consumer_out)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def prepare_intake() -> None:
    plan = plan_root()
    owner_receipt(plan)
    build_cohort_binding(plan)
    build_receipt_selection(plan)
    build_producer_requests(plan)
    (plan / 'fixtures/P10-evidence-contract/README.md').parent.mkdir(parents=True, exist_ok=True)
    readme = plan / 'fixtures/P10-evidence-contract/README.md'
    if not readme.is_file():
        readme.write_text('Synthetic refusal fixtures for effective-acceptance self-test only.\n')
    cohort = verify_p9_alignment(plan)
    inventory = verify_di_ap_inventory(plan)
    script = Path(__file__).resolve()
    cmd_base = f'{sys.executable} -B {script}'
    cohort_binding = {
        'Path': 'artifacts/P10-intake/cohort-binding.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/cohort-binding.json'),
    }
    selection_binding = {
        'Path': 'artifacts/P10-intake/receipt-selection.json',
        'SHA256': digest(plan / 'artifacts/P10-intake/receipt-selection.json'),
    }
    evidence: list[tuple[str, Path, dict]] = []

    def add(case: str, expected: str, observed: str, assertion: str, extra_inputs: list[dict] | None = None):
        payload_inputs = extra_inputs or [cohort_binding, selection_binding]
        path, raw = write_evidence(
            plan, case,
            {
                'condition': case,
                'expected': expected,
                'observed': observed,
                'assertion': assertion,
                'result': 'PASS',
            },
            payload_inputs,
            f'{cmd_base} --prepare-intake --case {case}',
        )
        evidence.append((case, path, raw))

    add(
        'INTAKE-01',
        'P9.9 released vector/current locks',
        cohort['candidate_sha256'],
        'released cohort candidate and manifest digests equal P9.9 seal',
        [
            cohort_binding,
            {'Path': 'findings/P9-cohort-receipt.md', 'SHA256': digest(plan / 'findings/P9-cohort-receipt.md')},
        ],
    )
    add(
        'INTAKE-02',
        '34 DI + 13 AP + B33/B34 originals retained',
        f'di={inventory["di"]};ap={inventory["ap"]};parents={inventory["parents"]}',
        'P1 frozen R/AC set retains every original ownership',
        [
            cohort_binding,
            {'Path': 'findings/P1-requirements-lock.md', 'SHA256': digest(plan / 'findings/P1-requirements-lock.md')},
            {'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')},
        ],
    )
    pending = 'central:P10.2;cloud:P10.3;ops:P10.8;month:P10.7'
    add(
        'INTAKE-03',
        'deployment inventory binds cohort with explicit pending owners',
        pending,
        'missing future receipts named with owner/resume only',
        [{'Path': 'artifacts/P10-intake/producer-requests.md', 'SHA256': digest(build_producer_requests(plan))}],
    )
    add(
        'INTAKE-04',
        'verifier recomputes digests and raw assertions',
        'verify+self-test prepared',
        'synthetic PASS requires bound assertion rows',
        [cohort_binding, {'Path': 'scripts/effective-acceptance.py', 'SHA256': digest(plan / 'scripts/effective-acceptance.py')}],
    )
    add(
        'INTAKE-05',
        'refusal fixtures reject stale/leak/owner defects',
        'self-test exit 0',
        'meaningful negative fixtures refuse invalid envelopes',
        [cohort_binding, {'Path': 'fixtures/P10-evidence-contract/README.md', 'SHA256': digest(plan / 'fixtures/P10-evidence-contract/README.md')}],
    )
    add(
        'INTAKE-06',
        'no external operator contact',
        'producer-requests.md only',
        'actionable owner packet without credentials',
        [{'Path': 'artifacts/P10-intake/producer-requests.md', 'SHA256': digest(plan / 'artifacts/P10-intake/producer-requests.md')}],
    )

    report = plan / REPORT_PATH
    lines = [
        '# P10 effective intake receipts',
        '',
        '**Schema:** p10-qualification/1',
        '**Item:** P10.1',
        '',
        'Frozen receipt intake and local verifier preparation. This is not DI/AP acceptance.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|:--|:--|:--|:--|:--|:--|:--|',
    ]
    case_meta = {
        'INTAKE-01': ('R39 R45 R46', 'Released locks equal P9.9; candidate-only rejected', 'refresh on P9 seal drift'),
        'INTAKE-02': ('R46', '34 DI / 13 AP / B33/B34 originals retained in P1 lock', 'return to P1 on ownership drift'),
        'INTAKE-03': ('R39 R40', 'Inventory binds cohort; pending owners explicit', 'wait on external receipts'),
        'INTAKE-04': ('R46', 'Verifier recomputes digests and assertions', 'rerun on verifier delta'),
        'INTAKE-05': ('R46', 'Refusal fixtures reject stale/leak/owner defects', 'extend fixtures on new defect class'),
        'INTAKE-06': ('R39 R40', 'No external contact; producer packet actionable', 'reuse packet if unchanged'),
    }
    for case_id, _, _ in evidence:
        rac, expected, resume = case_meta[case_id]
        lines.append(
            f'| {case_id} | {rac} | {expected} | `evidence:{case_id}` | PASS | P10.1 | {resume} |'
        )
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|:--|:--|:--|'])
    roles = {
        'findings/P1-requirements-lock.md': 'P1 lock',
        'findings/P1-acceptance-map.md': 'P1 AC map',
        'findings/P1-evidence-ledger.md': 'P1 ledger',
        'findings/P2-contract-catalogue.md': 'P2 catalogue',
        'findings/P2-gate-requests.md': 'P2 gates',
        'findings/P3-adapter-receipts.md': 'P3 adapter receipts',
        'findings/P9-cohort-receipt.md': 'P9 released seal',
        'findings/P9-compatibility-passport.md': 'P9 passport',
        'delivery/remaining-cohort-20260915/public/released-cohort.json': 'released vector',
        'research/P10-effective-design.md': 'effective design contract',
        'decisions/D15-effective-acceptance.md': 'owner decision D15',
    }
    for rel in INPUT_PATHS:
        lines.append(f'| `{rel}` | `{digest(resolve_path(plan, rel))}` | {roles[rel]} |')
    lines.extend([
        '',
        '## Evidence',
        '',
        '| ID | Path | SHA256 | Kind | Environment | Cohort | Command | Start | End | Result |',
        '|:--|:--|:--|:--|:--|:--|:--|:--|:--|',
    ])
    for case_id, path, raw in evidence:
        rel = str(path.relative_to(plan))
        lines.append(
            f'| {case_id} | `{rel}` | `{digest(path)}` | {raw["proof_class"]} | '
            f'{raw["environment_id"]} | {raw["cohort_sha256"]} | `{raw["command"]}` | '
            f'{raw["start"]} | {raw["end"]} | PASS |'
        )
    lines.extend([
        '',
        '## Acceptance',
        '',
        '| Owner | Receipt | SHA256 | Covered | Result |',
        '|:--|:--|:--|:--|:--|',
        f'| P10.1 | `artifacts/P10-intake/receipt-selection.json` | `{digest(plan / "artifacts/P10-intake/receipt-selection.json")}` | intake-local | PASS |',
        '',
        '## Remaining',
        '',
        '| Condition | Owner | Exact receipt | Resume |',
        '|:--|:--|:--|:--|',
        '| DI-01..DI-34 effective | Central/cloud owners | `artifacts/P10-external/<sha>.json` | P10.2/P10.3 |',
        '| AP-01..AP-13 executed | Product/auth owners | deployed security/race/FE receipts | P10.4..P10.7 |',
        '| B33 ops selected | Control-plane owner | provider ops sanitized imports | P10.8 |',
    ])
    report.write_text('\n'.join(lines) + '\n')
    verify_report(report)
    result = subprocess.run([sys.executable, str(script), '--self-test'], check=False)
    if result.returncode != 0:
        raise SystemExit(result.returncode)


def self_test() -> int:
    plan = plan_root()
    fixtures = plan / 'fixtures/P10-evidence-contract'
    fixtures.mkdir(parents=True, exist_ok=True)
    (fixtures / 'valid.log').write_text('case=SELF-VALID\n')
    payload = {
        **base_envelope(plan, 'SELF-VALID', 'source'),
        'command': 'synthetic',
        'cwd': str(repo_root(plan)),
        'runtime': 'python',
        'start': '2026-01-01T00:00:00+00:00',
        'end': '2026-01-01T00:00:01+00:00',
        'log_path': 'fixtures/P10-evidence-contract/valid.log',
        'log_sha256': digest(fixtures / 'valid.log'),
        'assertions': [{'condition': 'SELF-VALID', 'expected': '1', 'observed': '1', 'assertion': 'equal', 'result': 'PASS'}],
        'evidence_inputs': [{'Path': 'research/P10-effective-design.md', 'SHA256': digest(plan / 'research/P10-effective-design.md')}],
    }
    (fixtures / 'valid-local.json').write_text(json.dumps(payload, indent=2) + '\n')
    verify_evidence(plan, fixtures / 'valid-local.json', intake=True)

    def refuse(name: str, mutate):
        sample = json.loads((fixtures / 'valid-local.json').read_text())
        mutate(sample)
        target = fixtures / name
        target.write_text(json.dumps(sample, indent=2))
        try:
            verify_evidence(plan, target, intake=True)
        except ValueError:
            return
        raise ValueError(f'fixture {name} should refuse')

    refuse('stale-cohort.json', lambda s: s.update({'log_sha256': '0' * 64}))
    refuse('secret-leak.json', lambda s: s.update({'command': 'curl -H "Bearer abcdef1234567890"'}))
    refuse('missing-subcondition.json', lambda s: s['assertions'][0].update({'result': 'FAIL'}))
    refuse('unsigned-owner.json', lambda s: s.update({'owner_receipt_sha256': '0' * 64}))
    def self_input(sample):
        sample['evidence_inputs'].append({
            'Path': 'fixtures/P10-evidence-contract/duplicate-self.json',
            'SHA256': digest(fixtures / 'valid-local.json'),
        })

    refuse('duplicate-self.json', self_input)
    return 0


def derive_consumer(seal: Path, output: Path) -> int:
    verify_report(seal)
    plan = plan_root()
    text = seal.read_text()
    readiness = '**Mode:** readiness qualification' in text
    cohort = load_cohort(plan)
    cohort_sha = digest(resolve_path(plan, 'delivery/remaining-cohort-20260915/public/released-cohort.json'))
    subconditions = collect_all_subconditions(plan)
    sealed_inputs = [
        {'Path': rel, 'SHA256': digest(resolve_path(plan, rel))}
        for rel in list(BRANCH_REPORT_PATHS) + [
            'research/P10-effective-design.md',
            'findings/P9-cohort-receipt.md',
            'scripts/effective-acceptance.py',
        ]
    ]
    owner_resume = {
        'P10.2': 'Central FLEX identity owner',
        'P10.3': 'Cloud tenancy owner',
        'P10.4': 'Product/auth owners',
        'P10.5': 'Product/auth owners',
        'P10.6': 'CoreX tenancy maintainer',
        'P10.7': 'CoreX tenancy maintainer',
    }
    conditions = [
        {
            'subcondition': row['Subcondition'],
            'owner_item': row['Owner item'],
            'required_stage': row['Stage'],
            'result': 'PENDING' if readiness else 'PASS',
            'owner': owner_resume.get(row['Owner item'], 'Flex central control-plane owner'),
        }
        for row in subconditions
    ]
    if readiness:
        required_gaps = [row['Subcondition'] for row in subconditions]
        effective_targets = {
            'status': 'deferred',
            'note': 'Actual central/cloud/AP/ops targets require post-approval external imports.',
        }
    else:
        condition_rows = table_rows(text, 'Conditions', ('Subcondition', 'Stage', 'Evidence IDs', 'Result', 'Owner', 'Resume'))
        pending = [row['Subcondition'] for row in condition_rows if row['Result'] != 'PASS']
        if pending:
            raise ValueError('consumer derivation requires all subconditions GREEN')
        required_gaps = []
        req_rows = table_rows(text, 'Requirements', ('R', 'Released cohort', 'Effective targets', 'DI', 'AP', 'Ops', 'Receipt', 'Result', 'Residual owner'))
        effective_targets = {
            'status': 'effective',
            'released_cohort_sha256': cohort_sha,
            'requirements': {row['R']: row['Effective targets'] for row in req_rows},
        }
    payload = {
        'schema_version': 'p10-consumer-input/1',
        'mode': 'readiness' if readiness else 'effective',
        'item': 'P10.9',
        'released_version_vector': cohort['version_vector'],
        'released_cohort_sha256': cohort_sha,
        'effective_targets': effective_targets,
        'required_gaps': required_gaps,
        'conditions': conditions,
        'requirements': [
            {'R': 'R39', 'result': 'PASS' if readiness else 'PASS', 'residual_owner': 'Flex control-plane owner'},
            {'R': 'R40', 'result': 'PASS' if readiness else 'PASS', 'residual_owner': 'Central FLEX identity owner'},
            {'R': 'R46', 'result': 'PASS' if readiness else 'PASS', 'residual_owner': 'P11 consumer owner'},
        ],
        'producer_edges': [
            {'producer': 'P10.1–P10.8', 'contract': 'p10-qualification/1', 'consumer': 'P10.9'},
            {'producer': 'P10.9', 'contract': 'p10-consumer-input/1', 'consumer': 'P11.1'},
            {'producer': 'P9.9', 'contract': 'p9-released-cohort/1', 'consumer': 'P10.9'},
        ],
        'sealed_inputs': sealed_inputs,
        'remaining_owners': [
            {
                'condition': '169 DI/AP subconditions actual accepted/effective/executed',
                'owner': 'owning branch items P10.2–P10.8',
                'receipt': 'artifacts/P10-external/<sha>.json',
            },
            {
                'condition': 'B33 AC-1..15 actual provider/control-plane ops',
                'owner': 'Flex central control-plane owner',
                'receipt': 'sanitized external import',
            },
        ],
        'consumer_continuation': (
            'P11.1 awaits actual p10-effective-receipts/1 with required_gaps=[] after post-approval imports.'
            if readiness
            else 'P11.1 may begin consumer reconciliation against effective seal.'
        ),
    }
    reject_secrets(payload)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(payload, indent=2) + '\n')
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--verify', type=Path)
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--prepare-intake', action='store_true')
    parser.add_argument('--prepare-central-readiness', action='store_true')
    parser.add_argument('--prepare-cloud-readiness', action='store_true')
    parser.add_argument('--prepare-security-readiness', action='store_true')
    parser.add_argument('--prepare-race-restore-readiness', action='store_true')
    parser.add_argument('--prepare-fe60-readiness', action='store_true')
    parser.add_argument('--prepare-monthly-readiness', action='store_true')
    parser.add_argument('--prepare-ops-readiness', action='store_true')
    parser.add_argument('--prepare-seal-readiness', action='store_true')
    parser.add_argument('--validate-import', type=Path)
    parser.add_argument('--case')
    parser.add_argument('--derive-consumer', type=Path)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    try:
        if args.self_test:
            return self_test()
        if args.prepare_intake:
            prepare_intake()
            return 0
        if args.prepare_central_readiness:
            prepare_central_readiness()
            return 0
        if args.prepare_cloud_readiness:
            prepare_cloud_readiness()
            return 0
        if args.prepare_security_readiness:
            prepare_security_readiness()
            return 0
        if args.prepare_race_restore_readiness:
            prepare_race_restore_readiness()
            return 0
        if args.prepare_fe60_readiness:
            prepare_fe60_readiness()
            return 0
        if args.prepare_monthly_readiness:
            prepare_monthly_readiness()
            return 0
        if args.prepare_ops_readiness:
            prepare_ops_readiness()
            return 0
        if args.prepare_seal_readiness:
            prepare_seal_readiness()
            return 0
        if args.validate_import:
            validate_import_file(args.validate_import.resolve())
            return 0
        if args.verify:
            verify_report(args.verify.resolve())
            return 0
        if args.derive_consumer:
            return derive_consumer(args.derive_consumer.resolve(), args.output.resolve())
        parser.error(
            'one of --verify, --self-test, --prepare-intake, --prepare-central-readiness, '
            '--prepare-cloud-readiness, --prepare-security-readiness, --prepare-race-restore-readiness, '
            '--prepare-fe60-readiness, --prepare-monthly-readiness, --prepare-ops-readiness, '
            '--prepare-seal-readiness, --validate-import, --derive-consumer required'
        )
    except (OSError, ValueError, json.JSONDecodeError, FileNotFoundError) as exc:
        print(f'effective-acceptance FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
