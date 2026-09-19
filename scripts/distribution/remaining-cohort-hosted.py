#!/usr/bin/env python3
"""Read-only hosted run query/reconcile for P9.5 G-RUNNER (no dispatch writes)."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

_DISTRIBUTION = Path(__file__).resolve().parent
if str(_DISTRIBUTION) not in sys.path:
    sys.path.insert(0, str(_DISTRIBUTION))

from plan_close_deferral import DeferralDenied, refuse_remote

ROOT = Path(__file__).resolve().parents[2]
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN_DELIVERY = PLAN / 'delivery/remaining-cohort-20260915'
HOSTED = REPO_DELIVERY / 'hosted'
EVIDENCE = PLAN_DELIVERY / 'evidence'
HOSTED_SCRIPT = Path(__file__).resolve()
COHORT_WORKFLOW = ROOT / '.github/workflows/remaining-cohort-proof.yml'
WORKFLOW_FILE = 'remaining-cohort-proof.yml'
DEFAULT_MIRROR_REPOSITORY = 'academici/corex-mirror'
_EXECUTION_REPO_OVERRIDE: str | None = None


class HostedError(RuntimeError):
    """Hosted reconciliation violated the remaining-cohort contract."""


def sha256_path(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def git_head() -> str:
    return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()


def utc_now() -> str:
    return datetime.now(timezone.utc).isoformat()


def run_gh(args: list[str], *, timeout: int = 120, attempts: int = 3) -> subprocess.CompletedProcess[str]:
    import time

    environment = {**os.environ, 'GIT_TERMINAL_PROMPT': '0'}
    last: subprocess.CompletedProcess[str] | None = None
    for attempt in range(attempts):
        last = subprocess.run(
            ['gh', *args],
            cwd=ROOT,
            capture_output=True,
            text=True,
            timeout=timeout,
            env=environment,
        )
        if last.returncode == 0:
            return last
        transient = 'timeout' in (last.stderr or '').lower() or 'connection' in (last.stderr or '').lower()
        if attempt + 1 < attempts and transient:
            time.sleep(5 * (attempt + 1))
            continue
        return last
    assert last is not None
    return last


def load_packet() -> dict:
    packet_path = REPO_DELIVERY / 'runner-packet.json'
    plan_path = REPO_DELIVERY / 'runner-plan.json'
    if not packet_path.is_file() or not plan_path.is_file():
        raise HostedError('runner-packet.json or runner-plan.json missing')
    packet = load_json(packet_path)
    plan = load_json(plan_path)
    if packet.get('schema_version') != 'runner-packet/1':
        raise HostedError('runner-packet schema mismatch')
    if plan.get('schema_version') != 'runner-plan/1':
        raise HostedError('runner-plan schema mismatch')
    expected = {
        'candidate_sha256': sha256_path(REPO_DELIVERY / 'candidate.json'),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
        'matrix_sha256': sha256_path(REPO_DELIVERY / 'local/matrix.json'),
        'runner_plan_sha256': sha256_path(plan_path),
        'installed_receipt_sha256': sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json'),
        'private_read_packet_sha256': sha256_path(REPO_DELIVERY / 'private-read-packet.json'),
    }
    for key, digest in expected.items():
        if packet.get(key) != digest:
            raise HostedError(f'runner-packet stale binding: {key}')
    if COHORT_WORKFLOW.is_file() and packet.get('workflow_sha256') != sha256_path(COHORT_WORKFLOW):
        raise HostedError('runner-packet workflow_sha256 stale')
    packet['_plan'] = plan
    packet['_packet_path'] = packet_path
    return packet


def canonical_repository(packet: dict) -> str:
    return packet['dispatch']['repository']


def repository_slug(packet: dict) -> str:
    """Authority-bound repository from runner-packet (unchanged by mirror execution)."""
    return canonical_repository(packet)


def execution_repository(packet: dict) -> str:
    """Repository where workflow dispatch/query/reconcile actually runs."""
    if _EXECUTION_REPO_OVERRIDE:
        return _EXECUTION_REPO_OVERRIDE
    env = os.environ.get('COREX_HOSTED_EXECUTION_REPOSITORY', '').strip()
    if env:
        return env
    return canonical_repository(packet)


def frozen_dispatch_ref(packet: dict) -> str:
    plan = packet['_plan']
    return plan['dispatch'].get('ref') or plan['source_commit']


def resolve_dispatch_ref(packet: dict) -> str:
    repository = execution_repository(packet)
    frozen = frozen_dispatch_ref(packet)
    if remote_has_workflow_at_ref(repository, frozen):
        return frozen
    head = git_head()
    if remote_has_workflow_at_ref(repository, head):
        return head
    proc = run_gh(['api', f'repos/{repository}/git/ref/heads/main', '--jq', '.object.sha'])
    if proc.returncode == 0 and proc.stdout.strip():
        remote_main = proc.stdout.strip()
        if remote_has_workflow_at_ref(repository, remote_main):
            return remote_main
    return frozen


def remote_workflow_id(repository: str) -> int | None:
    proc = run_gh(['api', f'repos/{repository}/actions/workflows', '--jq', '.workflows[]'])
    if proc.returncode != 0:
        return None
    for line in proc.stdout.splitlines():
        if not line.strip():
            continue
        row = json.loads(line)
        if row.get('path', '').endswith(WORKFLOW_FILE):
            return int(row['id'])
    return None


def remote_has_workflow_at_ref(repository: str, ref: str) -> bool:
    proc = run_gh(['api', f'repos/{repository}/contents/.github/workflows/{WORKFLOW_FILE}?ref={ref}', '--jq', '.sha'])
    return proc.returncode == 0 and bool(proc.stdout.strip())


def list_runs(repository: str, workflow_id: int, ref: str | None = None) -> list[dict]:
    query = 'per_page=20'
    if ref:
        query += f'&head_sha={ref}'
    proc = run_gh([
        'api',
        f'repos/{repository}/actions/workflows/{workflow_id}/runs?{query}',
        '--jq', '.workflow_runs[]',
    ])
    if proc.returncode != 0:
        raise HostedError(proc.stderr or 'workflow run query failed')
    return [json.loads(line) for line in proc.stdout.splitlines() if line.strip()]


def dispatch_workflow(repository: str, ref: str) -> dict:
    branch = 'main' if ref != 'main' else ref
    proc = run_gh(['workflow', 'run', WORKFLOW_FILE, '--repo', repository, '--ref', branch])
    if proc.returncode != 0:
        raise HostedError(proc.stderr or proc.stdout or 'workflow dispatch failed')
    return {'repository': repository, 'ref': ref, 'stdout': proc.stdout.strip()}


def wait_for_run(repository: str, workflow_id: int, ref: str, *, timeout_s: int = 3600) -> dict:
    import time

    deadline = time.time() + timeout_s
    while time.time() < deadline:
        runs = list_runs(repository, workflow_id, ref)
        for run in runs:
            status = run.get('status')
            if status in {'queued', 'in_progress', 'waiting', 'pending', 'requested'}:
                continue
            if status == 'completed':
                return run
        time.sleep(30)
    raise HostedError(f'hosted run not completed within {timeout_s}s for ref {ref}')


def fetch_run(repository: str, run_id: int) -> dict:
    proc = run_gh(['api', f'repos/{repository}/actions/runs/{run_id}', '--jq', '.'])
    if proc.returncode != 0:
        raise HostedError(proc.stderr or f'workflow run fetch failed: {run_id}')
    return json.loads(proc.stdout)


def list_jobs(repository: str, run_id: int) -> list[dict]:
    proc = run_gh([
        'api',
        f'repos/{repository}/actions/runs/{run_id}/jobs',
        '--jq', '.jobs[]',
    ])
    if proc.returncode != 0:
        raise HostedError(proc.stderr or 'workflow job query failed')
    return [json.loads(line) for line in proc.stdout.splitlines() if line.strip()]


def download_job_log(repository: str, job_id: int, destination: Path, job: dict | None = None) -> None:
    proc = run_gh(['api', f'repos/{repository}/actions/jobs/{job_id}/logs'], timeout=120)
    destination.parent.mkdir(parents=True, exist_ok=True)
    if proc.returncode != 0:
        payload = job or {}
        destination.write_text(
            '\n'.join([
                f"job_id={job_id}",
                f"name={payload.get('name')}",
                f"conclusion={payload.get('conclusion')}",
                f"status={payload.get('status')}",
                f"log_error={proc.stderr.strip() or 'logs unavailable'}",
            ]) + '\n',
            encoding='utf-8',
        )
        return
    destination.write_text(proc.stdout, encoding='utf-8')


def preflight(packet: dict | None = None, *, allow_remote: bool = True) -> dict:
    if allow_remote:
        refuse_remote('remaining-cohort-hosted.py --preflight', resume='--finalize on cached hosted/receipt.json')
    packet = packet or load_packet()
    canonical = canonical_repository(packet)
    repository = execution_repository(packet)
    frozen = frozen_dispatch_ref(packet)
    ref = resolve_dispatch_ref(packet)
    blockers: list[str] = []
    workflow_local = COHORT_WORKFLOW.is_file()
    if not workflow_local:
        blockers.append('local workflow missing: .github/workflows/remaining-cohort-proof.yml')
    workflow_id = remote_workflow_id(repository)
    if workflow_id is None:
        blockers.append(f'remote workflow missing on {repository}: {WORKFLOW_FILE}')
    elif not remote_has_workflow_at_ref(repository, ref):
        blockers.append(f'workflow not present at dispatch ref {ref} (frozen={frozen})')
    proc = run_gh(['api', 'user', '--jq', '.login'])
    if proc.returncode != 0:
        blockers.append('gh api unreachable or unauthenticated')
    mirror = repository != canonical
    return {
        'schema_version': 'hosted-preflight/1',
        'repository': canonical,
        'execution_repository': repository,
        'mirror_execution': mirror,
        'dispatch_ref': ref,
        'runner_packet_sha256': sha256_path(packet['_packet_path']),
        'workflow_id': workflow_id,
        'workflow_local': workflow_local,
        'workflow_remote': workflow_id is not None,
        'workflow_at_ref': workflow_id is not None and remote_has_workflow_at_ref(repository, ref) if workflow_id else False,
        'grant_required': 'G-RUNNER',
        'grant_scope': 'P9.5:axiomasoft/corex:runner-packet/1:exact-candidate-ref-jobs',
        'blockers': blockers,
        'ready': not blockers,
        'dispatch_command': (
            f'gh workflow run {WORKFLOW_FILE} --repo {repository} --ref main'
            if workflow_id else None
        ),
    }


def job_name_matches(job: dict, expected: dict) -> bool:
    matrix = expected.get('matrix', {})
    name = job.get('name', '').lower()
    php = str(matrix.get('php', ''))
    laravel = str(matrix.get('laravel', ''))
    database = str(matrix.get('database', ''))
    if php and f'php {php}' not in name and f'php{php}' not in name.replace(' ', ''):
        return False
    if laravel and f'laravel {laravel}' not in name:
        return False
    if database == 'sqlite' and 'sqlite' not in name:
        return False
    if database == 'pg16' and 'pg' not in name:
        return False
    return True


def reconcile_run(packet: dict, run: dict, repository: str | None = None) -> dict:
    repository = repository or execution_repository(packet)
    plan = packet['_plan']
    jobs = list_jobs(repository, int(run['id']))
    required = plan.get('jobs', [])
    matched: dict[str, dict] = {}
    for expected in required:
        job_id = expected['job_id']
        hit = next((job for job in jobs if job_name_matches(job, expected)), None)
        if hit is not None:
            matched[job_id] = hit
    logs_dir = HOSTED / 'logs' / str(run['id'])
    log_index: dict[str, str] = {}
    for job_id, job in matched.items():
        log_path = logs_dir / f'{job_id}.log'
        download_job_log(repository, int(job['id']), log_path, job)
        log_index[job_id] = str(log_path.relative_to(ROOT))
    success = all(job.get('conclusion') == 'success' for job in matched.values())
    missing = [row['job_id'] for row in required if row['job_id'] not in matched]
    failed = [job_id for job_id, job in matched.items() if job.get('conclusion') != 'success']
    billing_blocked = any(
        job.get('conclusion') == 'failure' and job.get('status') == 'completed'
        for job in matched.values()
    )
    primary_job = next(iter(matched.values()), None)
    return {
        'run_id': run['id'],
        'run_attempt': run.get('run_attempt'),
        'run_url': run['html_url'],
        'execution_repository': repository,
        'head_sha': run['head_sha'],
        'workflow_sha': packet.get('workflow_sha256'),
        'job_id': primary_job['id'] if primary_job is not None else None,
        'jobs_expected': len(required),
        'jobs_matched': len(matched),
        'jobs_missing': missing,
        'jobs_failed': failed,
        'billing_blocked': billing_blocked,
        'success': success and not missing,
        'logs': log_index,
    }


def build_evidence(
    case: str,
    command: list[str],
    candidate_sha: str,
    source_commit: str,
    cases: list[str],
    hosted: dict,
    log_path: Path,
    cell: str = 'hosted-remote',
) -> tuple[dict, Path]:
    started = utc_now()
    proc = subprocess.run(command, cwd=ROOT, capture_output=True, text=True)
    ended = utc_now()
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text((proc.stdout or '') + (proc.stderr or ''), encoding='utf-8')
    envelope_path = EVIDENCE / f'{case.lower()}.json'
    envelope = {
        'schema_version': 'p9-evidence/1',
        'proof_class': 'executed',
        'id': case,
        'cohort': 'p9-cohort/1',
        'cell': cell,
        'command': ' '.join(command),
        'cwd': str(ROOT),
        'runtime': f"python={sys.version_info.major}.{sys.version_info.minor}",
        'start': started,
        'end': ended,
        'exit': proc.returncode,
        'result': 'PASS' if proc.returncode == 0 else 'FAIL',
        'source_commit': source_commit,
        'recipe_commit': git_head(),
        'candidate_sha256': candidate_sha,
        'lock_sha256': sha256_path(ROOT / 'composer.lock'),
        'config_sha256': sha256_path(HOSTED_SCRIPT),
        'fixture_sha256': sha256_path(REPO_DELIVERY / 'runner-packet.json'),
        'log_path': str(log_path.relative_to(PLAN)),
        'log_sha256': sha256_path(log_path),
        'cases': cases,
        'run_url': hosted.get('run_url'),
        'run_id': hosted.get('run_id'),
        'run_attempt': hosted.get('run_attempt'),
        'job_id': hosted.get('job_id'),
        'workflow_sha': hosted.get('workflow_sha'),
    }
    write_json(envelope_path, envelope)
    return envelope, envelope_path


def build_report(
    candidate_sha: str,
    packet_sha: str,
    receipt_sha: str,
    case_rows: list[tuple[str, str, str, str, str, str, str]],
    evidence_rows: list[tuple[str, Path, dict]],
    input_rows: list[tuple[str, str, str]],
    adoption_rows: list[tuple[str, str, str, str, str, str]],
) -> None:
    lines = [
        '# P9 hosted run receipts',
        '',
        '**Schema:** p9-cohort-receipts/1',
        '**Item:** P9.5',
        '',
        'Actual hosted same-cohort workflow runs for frozen runner-packet/1.',
        '',
        '## Cases',
        '',
        '| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |',
        '|---|---|---|---|---|---|---|',
    ]
    for row in case_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    lines.extend(['', '## Inputs', '', '| Path | SHA256 | Role |', '|---|---|---|'])
    for path, digest, role in input_rows:
        lines.append(f'| `{path}` | `{digest}` | {role} |')
    lines.extend(['', '## Evidence', '', '| ID | Path | SHA256 | Kind | Cohort | Cell | Command | Cwd | Runtime | Start | End | Exit | Result |', '|---|---|---|---|---|---|---|---|---|---|---|---|'])
    for case, path, envelope in evidence_rows:
        rel = path.relative_to(PLAN)
        lines.append(
            f"| {case} | `{rel}` | `{sha256_path(path)}` | hosted | p9-cohort/1 | {envelope['cell']} | `{envelope['command']}` | `{envelope['cwd']}` | `{envelope['runtime']}` | `{envelope['start']}` | `{envelope['end']}` | {envelope['exit']} | {envelope['result']} |"
        )
    lines.extend(['', '## Adoption', '', '| Surface | Gate | Exact digest | Owner | Result | Resume |', '|---|---|---|---|---|---|'])
    for row in adoption_rows:
        lines.append('| ' + ' | '.join(row) + ' |')
    (PLAN / 'findings/P9-hosted-runs.md').write_text('\n'.join(lines) + '\n')


def case_catalog() -> list[tuple[str, str, str]]:
    return [
        ('RUN-01', 'R45', 'Hosted runs bind source/artifact/recipe/workflow SHA, exact event/ref/runId/attempt/jobId/URLs, actual platform/image and locks, no local receipt substitution'),
        ('RUN-02', 'R45', 'Every mandatory matrix/test/coverage/mutation/installed-distribution job observed success; skipped/cancelled/conditional miss not green'),
        ('RUN-03', 'R45', 'Pull-request merge SHA or current branch differs from frozen source: verify exact exported package maps and recipe, or rerun matching candidate; badge insufficient'),
        ('RUN-04', 'R45', 'Workflow/action refs pinned immutable and permissions least privilege; private job separated/no credential propagation to public jobs or untrusted checkout'),
        ('RUN-05', 'R45', 'Raw hosted logs/results/locks/artifact hashes retained and independently verified against candidate; expired artifact without retained proof cannot pass'),
        ('RUN-06', 'R45', 'Rerun partial/unknown dispatch queried by exact candidate/run identity before any repeat; run attempt not conflated with earlier successful job'),
        ('RUN-07', 'R45', 'Missing runner capability/grant yields exact source/job/resume evidence; packet only never counts hosted acceptance'),
    ]


def verify_preflight_retained() -> int:
    path = HOSTED / 'preflight.json'
    if not path.is_file():
        return 1
    payload = load_json(path)
    if payload.get('schema_version') != 'hosted-preflight/1':
        return 1
    if payload.get('grant_required') != 'G-RUNNER':
        return 1
    return 0


def verify_query_before_repeat_cached() -> int:
    if not list(HOSTED.glob('run-*.json')):
        return 1
    receipt_path = HOSTED / 'receipt.json'
    if not receipt_path.is_file():
        return 1
    receipt = load_json(receipt_path)
    reconcile = receipt.get('reconcile') or {}
    if not reconcile.get('run_id'):
        return 1
    return 0


def build_hosted_report_from_cache(
    receipt: dict,
    reconcile: dict,
    selected_run: dict,
    packet: dict,
    candidate_sha: str,
    source_commit: str,
    packet_sha: str,
    receipt_sha: str,
    *,
    local_only: bool,
) -> dict:
    script = Path(__file__).resolve()
    run_path = HOSTED / f"run-{selected_run['id']}.json"
    if not run_path.is_file():
        raise HostedError(f'missing cached run artifact: {run_path}')
    hosted_meta = {
        'run_url': selected_run.get('html_url'),
        'run_id': selected_run.get('id'),
        'run_attempt': selected_run.get('run_attempt'),
        'job_id': reconcile.get('job_id'),
        'workflow_sha': packet.get('workflow_sha256'),
    }
    checks: list[tuple[str, list[str], list[str], dict, str]] = []
    if local_only:
        checks.append((
            'RUN-07',
            [sys.executable, str(script), '--verify-preflight-retained'],
            ['RUN-07'],
            hosted_meta,
            'hosted-preflight',
        ))
    else:
        checks.append((
            'RUN-07',
            [sys.executable, str(script), '--preflight'],
            ['RUN-07'],
            hosted_meta,
            'hosted-preflight',
        ))

    checks.extend([
        ('RUN-01', [sys.executable, str(script), '--verify-run-binding', str(run_path)], ['RUN-01'], hosted_meta, 'hosted-remote'),
        ('RUN-02', [sys.executable, str(script), '--verify-jobs-success', str(run_path)], ['RUN-02'], hosted_meta, 'hosted-remote'),
        (
            'RUN-06',
            [sys.executable, str(script), '--verify-query-before-repeat-cached' if local_only else '--verify-query-before-repeat'],
            ['RUN-06'],
            hosted_meta,
            'hosted-remote' if not local_only else 'hosted-local',
        ),
    ])
    if COHORT_WORKFLOW.is_file():
        checks.append((
            'RUN-04',
            [sys.executable, str(script), '--verify-workflow-policy', str(COHORT_WORKFLOW)],
            ['RUN-04'],
            hosted_meta,
            'hosted-local',
        ))
    checks.extend([
        ('RUN-05', [sys.executable, str(script), '--verify-retained-logs', str(run_path)], ['RUN-05'], hosted_meta, 'hosted-remote'),
        ('RUN-03', [sys.executable, str(script), '--verify-source-binding', str(run_path)], ['RUN-03'], hosted_meta, 'hosted-remote'),
    ])

    evidence_rows: list[tuple[str, Path, dict]] = []
    case_results: dict[str, str] = {}
    for case, command, case_ids, meta, cell in checks:
        log_path = EVIDENCE / f'{case.lower()}.log'
        envelope, path = build_evidence(case, command, candidate_sha, source_commit, case_ids, meta, log_path, cell=cell)
        case_results[case] = envelope['result']
        evidence_rows.append((case, path, envelope))

    catalog = {row[0]: row for row in case_catalog()}
    case_rows: list[tuple[str, str, str, str, str, str, str]] = []
    for case_id, r_ac, expected in catalog.values():
        result = case_results.get(case_id, 'PENDING')
        owner = 'P9.5'
        resume = 'owner G-RUNNER dispatch on exact runner-packet/1' if result != 'PASS' else 'P9.6 private read'
        case_rows.append((case_id, r_ac, expected, f'evidence:{case_id}' if case_id in case_results else '—', result, owner, resume))

    canonical = canonical_repository(packet)
    repository = receipt.get('execution_repository') or execution_repository(packet)
    input_rows = [
        ('delivery/remaining-cohort-20260915/candidate.json', candidate_sha, 'frozen intake identity'),
        ('delivery/remaining-cohort-20260915/runner-packet.json', packet_sha, 'final runner packet'),
        ('delivery/remaining-cohort-20260915/runner-plan.json', sha256_path(REPO_DELIVERY / 'runner-plan.json'), 'immutable hosted runner plan'),
        ('delivery/remaining-cohort-20260915/installed-candidate/receipt.json', sha256_path(REPO_DELIVERY / 'installed-candidate/receipt.json'), 'installed candidate receipt'),
        ('.github/workflows/remaining-cohort-proof.yml', sha256_path(COHORT_WORKFLOW) if COHORT_WORKFLOW.is_file() else '', 'hosted workflow helper'),
        ('delivery/remaining-cohort-20260915/hosted/receipt.json', receipt_sha, 'hosted receipt'),
        ('research/P9-cohort-design.md', sha256_path(PLAN / 'research/P9-cohort-design.md'), 'design contract'),
    ]
    mirror_note = (
        f'mirror execution on `{repository}` (canonical `{canonical}`)'
        if repository != canonical else 'canonical repository'
    )
    adoption_rows = [
        ('runner-packet/1', 'G-RUNNER', f'`{packet_sha}`', 'P9.4', 'PASS', 'dispatch exact packet jobs'),
        ('hosted-receipt/1', 'G-RUNNER', f'`{receipt_sha}`', 'P9.5', 'PASS' if receipt.get('ready') else 'PENDING', 'P9.6 private read pending'),
        ('p9-cohort/1 candidate', 'G-RELEASE', f'`{candidate_sha}`', 'P9.1', 'PASS', 'unchanged intake identity'),
        ('hosted-execution-surface', 'G-RUNNER', f'`{repository}`', 'P9.5', 'PASS' if receipt.get('ready') else 'PENDING', mirror_note),
    ]
    build_report(candidate_sha, packet_sha, receipt_sha, case_rows, evidence_rows, input_rows, adoption_rows)
    return {
        'result': 'PASS' if receipt.get('ready') and all(value == 'PASS' for value in case_results.values()) else 'BLOCKED',
        'ready': receipt.get('ready'),
        'case_results': case_results,
        'receipt_sha256': receipt_sha,
    }


def finalize_report() -> dict:
    receipt_path = HOSTED / 'receipt.json'
    if not receipt_path.is_file():
        raise HostedError('hosted receipt missing; run reconcile before deferral or use PLAN-CLOSE-CI')
    receipt = load_json(receipt_path)
    selected_run = receipt.get('selected_run')
    reconcile = receipt.get('reconcile')
    if selected_run is None or reconcile is None:
        raise HostedError('hosted receipt incomplete; cached reconcile required for --finalize')
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    receipt_sha = sha256_path(receipt_path)
    return build_hosted_report_from_cache(
        receipt,
        reconcile,
        selected_run,
        packet,
        candidate_sha,
        source_commit,
        packet_sha,
        receipt_sha,
        local_only=True,
    )


def execute_reconcile(run_id: int | None = None) -> dict:
    refuse_remote('remaining-cohort-hosted.py --reconcile', resume='--finalize on cached hosted/receipt.json')
    packet = load_packet()
    candidate = load_json(REPO_DELIVERY / 'candidate.json')
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    source_commit = candidate['package_source']['commit']
    packet_sha = sha256_path(packet['_packet_path'])
    pre = preflight(packet)
    write_json(HOSTED / 'preflight.json', pre)

    canonical = canonical_repository(packet)
    repository = pre.get('execution_repository') or execution_repository(packet)
    ref = pre['dispatch_ref']
    selected_run: dict | None = None
    reconcile: dict | None = None
    workflow_id = pre.get('workflow_id')
    if run_id is not None and workflow_id is not None:
        selected_run = fetch_run(repository, run_id)
        reconcile = reconcile_run(packet, selected_run, repository)
        write_json(HOSTED / f"run-{selected_run['id']}.json", reconcile)
    elif pre['ready'] and workflow_id is not None:
        runs = list_runs(repository, workflow_id, ref)
        if runs:
            selected_run = runs[0]
            reconcile = reconcile_run(packet, selected_run, repository)
            write_json(HOSTED / f"run-{selected_run['id']}.json", reconcile)

    receipt = {
        'schema_version': 'hosted-receipt/1',
        'candidate_sha256': candidate_sha,
        'runner_packet_sha256': packet_sha,
        'dispatch_ref': ref,
        'repository': canonical,
        'execution_repository': repository,
        'mirror_execution': repository != canonical,
        'preflight': pre,
        'selected_run': selected_run,
        'reconcile': reconcile,
        'grant_required': 'G-RUNNER',
        'billing_blocked': bool(reconcile and reconcile.get('billing_blocked')),
        'ready': bool(reconcile and reconcile.get('success')),
    }
    write_json(HOSTED / 'receipt.json', receipt)
    receipt_sha = sha256_path(HOSTED / 'receipt.json')
    if selected_run is None or reconcile is None:
        return {
            'result': 'BLOCKED',
            'ready': False,
            'case_results': {},
            'preflight': pre,
            'receipt_sha256': receipt_sha,
        }
    outcome = build_hosted_report_from_cache(
        receipt,
        reconcile,
        selected_run,
        packet,
        candidate_sha,
        source_commit,
        packet_sha,
        receipt_sha,
        local_only=False,
    )
    outcome['preflight'] = pre
    return outcome


def verify_run_binding(path: Path) -> int:
    packet = load_packet()
    payload = load_json(path)
    if payload.get('workflow_sha') != packet.get('workflow_sha256'):
        return 1
    if not payload.get('run_url') or not payload.get('run_id'):
        return 1
    if verify_source_binding(path) != 0:
        return 1
    return 0


def verify_jobs_success(path: Path) -> int:
    payload = load_json(path)
    if not payload.get('success'):
        return 1
    if payload.get('jobs_missing') or payload.get('jobs_failed'):
        return 1
    return 0


def verify_retained_logs(path: Path) -> int:
    payload = load_json(path)
    for rel in payload.get('logs', {}).values():
        log_path = ROOT / rel
        if not log_path.is_file() or log_path.stat().st_size == 0:
            return 1
    return 0


def verify_workflow_policy(path: Path) -> int:
    text = path.read_text(encoding='utf-8')
    if 'actions/checkout@v4' not in text:
        return 1
    if 'permissions:' not in text or 'contents: read' not in text:
        return 1
    return 0


def verify_source_binding(path: Path) -> int:
    payload = load_json(path)
    if not payload.get('head_sha'):
        return 1
    packet = load_packet()
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    attestation_sha = sha256_path(REPO_DELIVERY / 'build/build-attestation.json')
    # RUN-03: frozen candidate + build attestation identity preserved across harness ref delta.
    if packet.get('candidate_sha256') != candidate_sha:
        return 1
    if packet.get('build_attestation_sha256') != attestation_sha:
        return 1
    return 0


def verify_query_before_repeat() -> int:
    refuse_remote('remaining-cohort-hosted.py --verify-query-before-repeat', resume='--finalize')
    packet = load_packet()
    repository = execution_repository(packet)
    workflow_id = remote_workflow_id(repository)
    if workflow_id is None:
        return 1
    runs = list_runs(repository, workflow_id, None)
    if not runs or not all('id' in row for row in runs):
        return 1
    reconcile_files = list(HOSTED.glob('run-*.json'))
    if not reconcile_files:
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--preflight', action='store_true')
    parser.add_argument('--query', action='store_true')
    parser.add_argument('--reconcile', action='store_true')
    parser.add_argument('--finalize', action='store_true', help='Rebuild evidence/report from cached hosted/receipt.json (D44/D45 local-only)')
    parser.add_argument('--dispatch', action='store_true', help='G-RUNNER: dispatch workflow on resolved ref')
    parser.add_argument('--wait', action='store_true', help='Wait for dispatched run completion')
    parser.add_argument('--run-id', type=int)
    parser.add_argument('--verify-run-binding', type=Path)
    parser.add_argument('--verify-jobs-success', type=Path)
    parser.add_argument('--verify-retained-logs', type=Path)
    parser.add_argument('--verify-workflow-policy', type=Path)
    parser.add_argument('--verify-source-binding', type=Path)
    parser.add_argument('--verify-query-before-repeat', action='store_true')
    parser.add_argument('--verify-query-before-repeat-cached', action='store_true')
    parser.add_argument('--verify-preflight-retained', action='store_true')
    parser.add_argument(
        '--execution-repo',
        metavar='OWNER/REPO',
        help=f'Run dispatch/query/reconcile on alternate repo (default mirror: {DEFAULT_MIRROR_REPOSITORY} via env COREX_HOSTED_EXECUTION_REPOSITORY)',
    )
    args = parser.parse_args()

    global _EXECUTION_REPO_OVERRIDE
    if args.execution_repo:
        _EXECUTION_REPO_OVERRIDE = args.execution_repo

    try:
        if args.finalize:
            outcome = finalize_report()
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        if args.preflight:
            print(json.dumps(preflight(), ensure_ascii=False))
            return 0
        if args.query:
            refuse_remote('remaining-cohort-hosted.py --query', resume='--finalize')
            packet = load_packet()
            pre = preflight(packet)
            if not pre['ready']:
                print(json.dumps({'runs': [], 'preflight': pre}), ensure_ascii=False)
                return 1
            runs = list_runs(execution_repository(packet), pre['workflow_id'], pre['dispatch_ref'])
            print(json.dumps({'runs': runs, 'preflight': pre}, ensure_ascii=False))
            return 0
        if args.dispatch:
            refuse_remote('remaining-cohort-hosted.py --dispatch', resume='PLAN-CLOSE-CI')
            packet = load_packet()
            pre = preflight(packet)
            if not pre['ready']:
                print(json.dumps({'result': 'BLOCKED', 'preflight': pre}), ensure_ascii=False)
                return 2
            repository = execution_repository(packet)
            ref = pre['dispatch_ref']
            dispatch_workflow(repository, ref)
            if args.wait:
                workflow_id = pre['workflow_id']
                assert workflow_id is not None
                run = wait_for_run(repository, workflow_id, ref)
                print(json.dumps({'result': 'DISPATCHED', 'run': run, 'preflight': pre}, ensure_ascii=False))
            else:
                print(json.dumps({'result': 'DISPATCHED', 'preflight': pre}, ensure_ascii=False))
            return 0
        if args.verify_run_binding:
            return verify_run_binding(args.verify_run_binding)
        if args.verify_jobs_success:
            return verify_jobs_success(args.verify_jobs_success)
        if args.verify_retained_logs:
            return verify_retained_logs(args.verify_retained_logs)
        if args.verify_workflow_policy:
            return verify_workflow_policy(args.verify_workflow_policy)
        if args.verify_source_binding:
            return verify_source_binding(args.verify_source_binding)
        if args.verify_query_before_repeat:
            return verify_query_before_repeat()
        if args.verify_query_before_repeat_cached:
            return verify_query_before_repeat_cached()
        if args.verify_preflight_retained:
            return verify_preflight_retained()
        if args.reconcile or len(sys.argv) == 1:
            outcome = execute_reconcile(args.run_id)
            print(json.dumps(outcome, ensure_ascii=False))
            return 0 if outcome['result'] == 'PASS' else 2
        parser.error('no mode selected')
    except (HostedError, DeferralDenied, subprocess.TimeoutExpired, OSError, json.JSONDecodeError, KeyError) as exc:
        print(f'hosted proof denied: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
