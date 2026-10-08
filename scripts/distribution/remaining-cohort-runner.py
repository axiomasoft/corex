#!/usr/bin/env python3
"""Prepare immutable runner-plan/1 for P9.5 hosted dispatch (read-only by default)."""
from __future__ import annotations

import argparse
import hashlib
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
RECORD = ROOT / 'release/remaining-cohort-20260915'
RUNNER_SCRIPT = Path(__file__).resolve()
QUALITY_SCRIPT = Path(__file__).with_name('remaining-cohort-quality.py')
EXPORT_SCRIPT = Path(__file__).with_name('remaining-cohort-export.py')
PUBLISH_SCRIPT = Path(__file__).with_name('remaining-cohort-publish.py')

WORKFLOWS = {
    'ci': ROOT / '.github/workflows/ci.yml',
    'distribution_proof': ROOT / '.github/workflows/distribution-proof.yml',
    'remaining_cohort_proof': ROOT / '.github/workflows/remaining-cohort-proof.yml',
}


class RunnerError(RuntimeError):
    """Runner plan construction violated the remaining-cohort contract."""


def sha256_path(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def git_head() -> str:
    return subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()


def build_runner_plan(matrix_path: Path) -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    attestation_path = REPO_DELIVERY / 'build/build-attestation.json'
    for path in (candidate_path, attestation_path, matrix_path):
        if not path.is_file():
            raise RunnerError(f'missing prerequisite artifact: {path}')

    candidate = load_json(candidate_path)
    matrix = load_json(matrix_path)
    attestation = load_json(attestation_path)

    workflow_hashes = {name: sha256_path(path) for name, path in WORKFLOWS.items() if path.is_file()}
    if 'remaining_cohort_proof' not in workflow_hashes:
        raise RunnerError('remaining-cohort-proof workflow missing')

    jobs = []
    for row in matrix.get('unique_jobs', []):
        jobs.append({
            'job_id': row['job_id'],
            'workflow': 'remaining-cohort-proof.yml',
            'matrix': {
                'php': row['php'],
                'laravel': row['laravel'],
                'database': row['database'],
            },
            'packages': row['packages'],
            'profiles': row['profiles'],
            'required_success': True,
        })

    return {
        'schema_version': 'runner-plan/1',
        'repository': candidate['package_source']['repository'],
        'cohort_id': candidate['cohort_id'],
        'candidate_sha256': sha256_path(candidate_path),
        'build_attestation_sha256': sha256_path(attestation_path),
        'matrix_sha256': sha256_path(matrix_path),
        'source_commit': candidate['package_source']['commit'],
        'recipe_commits': {
            'remaining-cohort-export.py': sha256_path(EXPORT_SCRIPT),
            'remaining-cohort-quality.py': sha256_path(QUALITY_SCRIPT),
            'remaining-cohort-runner.py': sha256_path(RUNNER_SCRIPT),
            'remaining-cohort-publish.py': sha256_path(PUBLISH_SCRIPT) if PUBLISH_SCRIPT.is_file() else None,
            'git_head': git_head(),
        },
        'workflow_sha256': workflow_hashes,
        'dispatch': {
            'event': 'workflow_dispatch',
            'ref_type': 'commit',
            'ref': candidate['package_source']['commit'],
            'repository': 'axiomasoft/corex',
            'protected': False,
            'note': 'P9.5 G-RUNNER dispatch only; this plan does not execute remote writes',
        },
        'jobs': jobs,
        'quality_jobs': [
            {'workflow': 'ci.yml', 'required': True},
            {'workflow': 'remaining-cohort-proof.yml', 'required': True},
        ],
        'attestation_packages': len(attestation.get('packages', {})),
    }


def verify_runner_plan(plan_path: Path) -> int:
    plan = load_json(plan_path)
    if plan.get('schema_version') != 'runner-plan/1':
        return 1
    if plan.get('dispatch', {}).get('protected'):
        return 1
    if not plan.get('jobs'):
        return 1
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    if plan.get('candidate_sha256') != candidate_sha:
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--build', type=Path, required=True, help='Path to local/matrix.json')
    parser.add_argument('--verify', type=Path, help='Verify an existing runner-plan.json')
    args = parser.parse_args()

    if args.verify:
        return verify_runner_plan(args.verify)

    plan = build_runner_plan(args.build)
    output = REPO_DELIVERY / 'runner-plan.json'
    write_json(output, plan)
    print(json.dumps({'result': 'PASS', 'runner_plan_sha256': sha256_path(output), 'jobs': len(plan['jobs'])}))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
