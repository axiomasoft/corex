#!/usr/bin/env python3
"""Restore hash-bound, local ZIP dependencies only in disposable BC base trees."""
from __future__ import annotations

import argparse
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import subprocess
import zipfile


def stage(root: Path, base: str, work: Path) -> None:
    lock = json.loads(subprocess.check_output(['git', '-C', str(root), 'show', f'{base}:composer.lock']))
    required = set()
    for manifest in work.glob('packages/*/composer.json'):
        data = json.loads(manifest.read_text())
        required.update(data.get('require', {}))
        required.update(data.get('require-dev', {}))
    archives = subprocess.check_output(['git', '-C', str(root), 'ls-tree', '-r', '-z', '--name-only', 'HEAD'], text=True).split('\0')
    for package in lock.get('packages', []) + lock.get('packages-dev', []):
        dist = package.get('dist', {})
        url = dist.get('url', '')
        if package['name'] not in required or dist.get('type') != 'zip' or '://' in url or not url.endswith('.zip'):
            continue
        digest = dist.get('shasum', '')
        if not digest:
            raise ValueError(f'Missing archived dependency digest: {package["name"]}')
        matches = []
        for path in archives:
            if PurePosixPath(path).name != PurePosixPath(url).name:
                continue
            archive_path = root / path
            if not archive_path.is_file():
                continue
            # The worktree supplies hydrated Git LFS archives; the baseline
            # lock digest still binds every byte before extraction.
            blob = archive_path.read_bytes()
            if hashlib.sha1(blob).hexdigest() == digest:
                matches.append(blob)
        matches = list(set(matches))
        if len(matches) != 1:
            raise ValueError(f'Expected one digest-matched archive: {package["name"]}')
        with zipfile.ZipFile(io.BytesIO(matches[0])) as archive:
            members = archive.namelist()
            manifests = [name for name in members if PurePosixPath(name).name == 'composer.json']
            depth = min((len(PurePosixPath(name).parts) for name in manifests), default=0)
            manifests = [name for name in manifests if len(PurePosixPath(name).parts) == depth]
            if len(manifests) != 1:
                raise ValueError('Expected one archive root composer.json')
            prefix = str(PurePosixPath(manifests[0]).parent)
            prefix = '' if prefix == '.' else prefix + '/'
            destination = work / 'packages/_vendor' / package['name'].replace('/', '__')
            destination.mkdir(parents=True, exist_ok=True)
            for info in archive.infolist():
                if not info.filename.startswith(prefix) or info.is_dir():
                    continue
                relative = PurePosixPath(info.filename[len(prefix):])
                if relative.is_absolute() or '..' in relative.parts or not relative.parts:
                    raise ValueError('Unsafe archive path')
                target = destination.joinpath(*relative.parts)
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(archive.read(info))
            manifest = destination / 'composer.json'
            data = json.loads(manifest.read_text())
            if data.get('name') != package['name']:
                raise ValueError('Archive manifest package mismatch')
            data['version'] = package['version']
            manifest.write_text(json.dumps(data, indent=4) + '\n')


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', type=Path, required=True)
    parser.add_argument('--base', required=True)
    parser.add_argument('--work', type=Path, required=True)
    args = parser.parse_args()
    stage(args.root, args.base, args.work)
