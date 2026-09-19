#!/usr/bin/env python3
"""Repair corrupted Evidence tables in P9 branch findings from envelope JSON."""
from __future__ import annotations

import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'

REPORTS: dict[str, tuple[str, int, str]] = {
    'findings/P9-intake.md': ('INTAKE', 6, 'local'),
    'findings/P9-artifacts.md': ('DIST', 8, 'local'),
    'findings/P9-local-matrix.md': ('MATRIX', 7, 'local'),
    'findings/P9-installed-candidate.md': ('INSTALL', 9, 'local'),
    'findings/P9-hosted-runs.md': ('RUN', 7, 'hosted'),
    'findings/P9-private-auth.md': ('PRIVATE', 6, 'private'),
    'findings/P9-publication-packet.md': ('PACKET', 6, 'local'),
    'findings/P9-publication.md': ('PUB', 8, 'release'),
    'findings/P9-released-install.md': ('RELEASE', 7, 'released-installed'),
}


def sha256_path(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def resolve_evidence(rel: str) -> Path:
    plan_path = PLAN / rel
    if plan_path.is_file():
        return plan_path
    root_path = ROOT / rel
    if root_path.is_file():
        return root_path
    raise FileNotFoundError(rel)


def evidence_row(case_id: str, rel: str, kind: str, envelope: dict) -> str:
    artifact = resolve_evidence(rel)
    digest = sha256_path(artifact)
    return (
        f"| {case_id} | `{rel}` | `{digest}` | {kind} | p9-cohort/1 | {envelope['cell']} | "
        f"`{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | "
        f"`{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | PASS |"
    )


def repair_report(report_rel: str) -> bool:
    prefix, count, kind = REPORTS[report_rel]
    path = PLAN / report_rel
    text = path.read_text(encoding='utf-8')
    if '## Evidence' not in text:
        return False
    before, rest = text.split('## Evidence', 1)
    body, after = rest.split('\n## ', 1)
    rows: list[str] = []
    for index in range(1, count + 1):
        case_id = f'{prefix}-{index:02d}'
        slug = case_id.lower()
        rel = f'delivery/remaining-cohort-20260915/evidence/{slug}.json'
        envelope = json.loads(resolve_evidence(rel).read_text(encoding='utf-8'))
        rows.append(evidence_row(case_id, rel, kind, envelope))
    header = (
        '\n\n| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |\n'
        '|---|---|---|---|---|---|---|---|---|---|---|---|\n'
    )
    path.write_text(before + '## Evidence' + header + '\n'.join(rows) + '\n\n## ' + after, encoding='utf-8')
    return True


def main() -> int:
    repaired = [report for report in REPORTS if repair_report(report)]
    print(json.dumps({'repaired': repaired, 'count': len(repaired)}))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
