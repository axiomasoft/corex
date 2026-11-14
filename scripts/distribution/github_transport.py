"""GitHub publication transport; callers supply the admitted archive explicitly."""
from pathlib import Path
import json
import subprocess

from publish_guard import PublicationDenied, UnknownOutcome


def run(parts, *, cwd=None, allow_missing=False):
    result = subprocess.run(parts, cwd=cwd, text=True, capture_output=True)
    if result.returncode:
        if allow_missing and ('HTTP 404' in result.stderr
                              or 'HTTP 409' in result.stderr
                              and 'Git Repository is empty' in result.stderr):
            return None
        # Never persist arbitrary transport stderr: it may contain credentials.
        raise PublicationDenied(f'transport failed ({result.returncode}); reconcile before any retry')
    return result.stdout


def api(path, *, allow_missing=False):
    raw = run(['gh', 'api', path], allow_missing=allow_missing)
    return json.loads(raw) if raw is not None else None


class GitHubTransport:
    def __init__(self, label, record, staging, *, archive, write):
        self.record = record
        self.repository = record['name']
        self.local = staging / label
        self.archive = Path(archive)
        self.tag = record['tag_proposal']
        self.write = write
        self.writes = []

    def reconcile(self, operation):
        repository = self.repository
        if operation.kind == 'create':
            value = api(f'repos/{repository}', allow_missing=True)
            if value is None:
                return None
            return (operation.fingerprint if value['full_name'] == repository
                    and not value['private'] and not value['archived'] else 'collision')
        if operation.kind == 'actions-disable':
            return 'disabled' if not api(f'repos/{repository}/actions/permissions')['enabled'] else None
        if operation.kind in {'push', 'tag'}:
            ref = 'heads/main' if operation.kind == 'push' else f'tags/{self.tag}'
            value = api(f'repos/{repository}/git/ref/{ref}', allow_missing=True)
            return value['object']['sha'] if value else None
        value = api(f'repos/{repository}/releases/tags/{self.tag}', allow_missing=True)
        if value is None:
            return None
        assets = value['assets']
        if (value['draft'] or not value['prerelease'] or len(assets) != 1
                or assets[0]['name'] != self.archive.name):
            return 'collision'
        return assets[0].get('digest') or 'missing-asset-digest'

    def apply(self, operation):
        if not self.write:
            raise PublicationDenied('read-only mode forbids missing-result writes')
        repository = self.repository
        if operation.kind == 'create':
            command = ['gh', 'repo', 'create', repository, '--public']
        elif operation.kind == 'actions-disable':
            command = ['gh', 'api', '-X', 'PUT', f'repos/{repository}/actions/permissions', '-F', 'enabled=false']
        elif operation.kind in {'push', 'tag'}:
            if api(f'repos/{repository}/actions/permissions')['enabled']:
                raise PublicationDenied('Actions must be disabled before push')
            ref = 'refs/heads/main' if operation.kind == 'push' else f'refs/tags/{self.tag}'
            command = ['git', 'push', f'https://github.com/{repository}.git', f'{ref}:{ref}']
        else:
            command = ['gh', 'release', 'create', self.tag, str(self.archive), '--repo', repository,
                       '--verify-tag', '--prerelease', '--title', f'{repository} {self.record["version"]}',
                       '--notes', f'Sealed candidate {self.record["candidate_commit_sha1"]}. ZIP SHA256: {self.record["candidate_zip_sha256"]}. Actions disabled; no hosted-runner proof claimed.']
        self.writes.append(operation.kind)
        try:
            run(command, cwd=self.local)
        except PublicationDenied:
            raise UnknownOutcome() from None
