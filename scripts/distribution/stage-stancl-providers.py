#!/usr/bin/env python3
"""Stage sealed P4.1 stancl provider candidates for root Composer resolution on CI."""
from __future__ import annotations

import json
import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
P41_SOURCE = ROOT / 'plans/archive/2026.09.12-№2-COREX-APPROVED-SPECS/findings/P4.1-source'
STAGING = ROOT / '.composer-provider-staging'
ARCHIVE_NAMES = {
    'axiomasoft/stancl-tenancy': 'tenancy-candidate.zip',
    'axiomasoft/stancl-jobpipeline': 'jobpipeline-candidate.zip',
}


class StageError(RuntimeError):
    """Sealed provider staging violated the distribution contract."""


def stage() -> Path:
    if not (P41_SOURCE / 'packages.json').is_file():
        raise StageError(f'missing sealed provider metadata: {P41_SOURCE / "packages.json"}')

    if STAGING.is_dir():
        shutil.rmtree(STAGING)

    artifacts = STAGING / 'artifacts'
    artifacts.mkdir(parents=True)
    metadata = json.loads((P41_SOURCE / 'packages.json').read_text(encoding='utf-8'))

    for package, archive_name in ARCHIVE_NAMES.items():
        source = P41_SOURCE / archive_name
        if not source.is_file():
            raise StageError(f'sealed provider candidate missing: {source}')
        destination = artifacts / archive_name
        shutil.copyfile(source, destination)
        payload = next(iter(metadata['packages'][package].values()))
        payload['dist']['url'] = destination.resolve().as_uri()

    (STAGING / 'packages.json').write_text(
        json.dumps(metadata, ensure_ascii=False, indent=2, sort_keys=True) + '\n',
        encoding='utf-8',
    )
    return STAGING


def register_composer(cwd: Path, repository: str) -> None:
    proc = subprocess.run(
        ['composer', 'config', 'repositories.stancl-providers', 'composer', repository],
        cwd=cwd,
        capture_output=True,
        text=True,
    )
    if proc.returncode != 0:
        raise StageError(proc.stderr.strip() or proc.stdout.strip() or f'composer config failed in {cwd}')


def register() -> None:
    staging = stage()
    repository = staging.resolve().as_uri()
    targets = [ROOT]
    tenancy = ROOT / 'packages' / 'tenancy'
    if (tenancy / 'composer.json').is_file():
        targets.append(tenancy)
    for cwd in targets:
        register_composer(cwd, repository)
    print(json.dumps({'result': 'PASS', 'repository': repository, 'staging': str(staging), 'registered': [str(p) for p in targets]}))


def main() -> int:
    try:
        register()
    except StageError as exc:
        print(f'stancl provider staging denied: {exc}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
