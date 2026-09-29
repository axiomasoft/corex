#!/usr/bin/env python3
"""Stage pinned upstream provider artifacts for local Composer resolution."""
from __future__ import annotations

import argparse
import json
import os
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
LOCKED_ARCHIVES = dict(ARCHIVE_NAMES)


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


def portable_locks() -> None:
    # Composer install reads dist URLs from the lock rather than the staged
    # repository, so local archives must resolve inside the current checkout.
    for cwd in (ROOT, ROOT / 'packages/tenancy', ROOT / 'packages/access'):
        path = cwd / 'composer.lock'
        if not path.is_file():
            continue
        lock = json.loads(path.read_text(encoding='utf-8'))
        changed = False
        for package in lock.get('packages', []) + lock.get('packages-dev', []):
            archive = LOCKED_ARCHIVES.get(package['name'])
            dist = package.get('dist', {})
            url = dist.get('url', '')
            if archive is None or dist.get('type') != 'zip' or not url.endswith('/' + archive):
                continue
            relative = os.path.relpath(STAGING / 'artifacts' / archive, cwd)
            if url != relative:
                dist['url'] = relative
                changed = True
        if changed:
            path.write_text(json.dumps(lock, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')


def register() -> None:
    staging = stage()
    repository = staging.resolve().as_uri()
    targets = [ROOT]
    tenancy = ROOT / 'packages' / 'tenancy'
    if (tenancy / 'composer.json').is_file():
        targets.append(tenancy)
    root_manifest = json.loads((ROOT / 'composer.json').read_text(encoding='utf-8'))
    if 'axiomasoft/corex-access' in {**root_manifest.get('require', {}), **root_manifest.get('require-dev', {})}:
        targets.append(ROOT / 'packages/access')
    portable_locks()
    for cwd in targets:
        register_composer(cwd, repository)
    print(json.dumps({'result': 'PASS', 'repository': repository, 'staging': str(staging), 'registered': [str(p) for p in targets]}))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--portable-locks', action='store_true', help='normalise existing staged archive URLs without fetching providers')
    arguments = parser.parse_args()
    try:
        if arguments.portable_locks:
            portable_locks()
        else:
            register()
    except StageError as exc:
        print(f'stancl provider staging denied: {exc}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
