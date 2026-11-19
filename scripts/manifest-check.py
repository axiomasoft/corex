#!/usr/bin/env python3
"""Validate PHP platform, Laravel compatibility and monorepo lockstep constraints.

Checks every installable root (repository root + packages/*/composer.json) against the
platform and lockstep rules. Exit 0 = all rules hold; exit 1 = violations listed one per
line as `<root>: <code>: <detail>`. Run from the repository root; stdlib only.
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path.cwd()
ALIAS = '1.0.x-dev'
MONO_CONSTRAINT = '^1.0@dev'
PHP = '^8.4'
ILLUMINATE = '^13.0'
# Packman packages are pinned to ^0.1 (0.1.0) in the root package repositories
# and lockfile; unreleased ecosystem dependencies remain fixed to reviewed commits.
PACKMAN_CONSTRAINTS = {
    'axiomasoft/packman': '^0.1',
    'axiomasoft/packman-devtools': '^0.1',
    'axiomasoft/packman-testkit': '^0.1',
    'axiomasoft/packman-modules': '^0.1',
    'axiomasoft/packman-schema': '^0.1',
    'axiomasoft/packman-panels': '^0.1',
    'axiomasoft/packman-plugins': '^0.1',
}
ALLOWED_PRERELEASE = {
    'axiomasoft/azguard': '^2.0@dev',
    'axiomasoft/tenant': '^1.0@dev',
    'axiomasoft/tenant-laravel': '^1.0@dev',
    'axiomasoft/vaulter-core': '^1.0@dev',
    'axiomasoft/vaulter-media': '^1.0@dev',
}
# The AzGuard bridge stays outside the release set until its upstream is published.
UNRELEASED = {'axiomasoft/corex-access-azguard'}
VALID_STABILITY = {'stable', 'experimental', 'internal', 'deprecated', 'unreleased'}
STACK = {
    'pestphp/pest': '^5.2',
    'orchestra/testbench': '^11.3',
    'orchestra/testbench-core': '^11.5',
    'laravel/framework': '^13.0',
}


def load(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def pinned(constraint: str | None, version: str) -> bool:
    # Equal inclusive bounds preserve the exact pin while avoiding Composer's
    # heuristic warning about bare exact constraints in published manifests.
    return constraint == version or (not version.startswith('^') and constraint == f'>={version} <={version}')


def main() -> int:
    packages = {}
    for manifest in sorted([*ROOT.glob('packages/*/composer.json'), *ROOT.glob('products/*/composer.json')]):
        data = load(manifest)
        packages[data['name']] = (manifest.parent.name, data)
    mono = set(packages)

    def direct(data: dict, dev: bool) -> set[str]:
        names = set(data.get('require', {}))
        if dev:
            names |= set(data.get('require-dev', {}))
        return names & mono

    def closure(data: dict) -> set[str]:
        seen: set[str] = set()
        stack = list(direct(data, dev=True))
        while stack:
            name = stack.pop()
            if name in seen or name == data.get('name'):
                continue
            seen.add(name)
            stack.extend(direct(packages[name][1], dev=False))
        return seen

    violations: list[str] = []
    roots = [('ROOT', load(ROOT / 'composer.json'), True)]
    roots += [(packages[n][0], packages[n][1], False) for n in sorted(packages)]

    for label, data, is_root in roots:
        def bad(code: str, detail: str) -> None:
            violations.append(f'{label}: {code}: {detail}')

        requires = {**data.get('require', {}), **data.get('require-dev', {})}
        if is_root and 'axiomasoft/corex-access' not in requires:
            bad('golden-closure', 'root must install axiomasoft/corex-access and its upstreams for the all-package golden surface')
        if data.get('require', {}).get('php') != PHP:
            bad('php', f"require.php={data.get('require', {}).get('php')!r}, expected {PHP!r}")
        if data.get('minimum-stability') != 'stable':
            bad('stability', f"minimum-stability={data.get('minimum-stability')!r}")
        if data.get('prefer-stable') is not True:
            bad('stability', 'prefer-stable must be true')
        if not is_root and data.get('extra', {}).get('branch-alias', {}).get('dev-main') != ALIAS:
            bad('branch-alias', f'extra.branch-alias.dev-main must be {ALIAS!r}')
        stability = data.get('extra', {}).get('corex', {}).get('stability')
        if not is_root and stability not in VALID_STABILITY:
            bad('stability', f'unknown extra.corex.stability={stability!r}')
        if stability == 'unreleased' and data.get('name') not in UNRELEASED:
            bad('stability', f'stability=unreleased is reserved for {sorted(UNRELEASED)}')

        for name, constraint in sorted(requires.items()):
            if name in mono and constraint != MONO_CONSTRAINT:
                bad('mono-constraint', f'{name}={constraint!r}, expected {MONO_CONSTRAINT!r}')
            elif name.startswith('illuminate/') and constraint != ILLUMINATE:
                bad('illuminate', f'{name}={constraint!r}, expected {ILLUMINATE!r}')
            elif name in STACK and constraint != STACK[name]:
                bad('stack', f'{name}={constraint!r}, expected {STACK[name]!r}')
            elif name in PACKMAN_CONSTRAINTS and not pinned(constraint, PACKMAN_CONSTRAINTS[name]):
                bad('packman', f'{name}={constraint!r}, expected {PACKMAN_CONSTRAINTS[name]!r}')
            elif name not in mono and name not in ALLOWED_PRERELEASE and re.search(r'@(dev|alpha|beta|RC)|dev-|-alpha|-beta', constraint):
                bad('prerelease', f'{name}={constraint!r}')
            elif name in ALLOWED_PRERELEASE and not pinned(constraint, ALLOWED_PRERELEASE[name]):
                bad('prerelease', f'{name}={constraint!r}, expected exception {ALLOWED_PRERELEASE[name]!r}')

        missing = sorted(closure(data) - direct(data, dev=True))
        for name in missing:
            bad('closure', f'transitive monorepo package {name} not required in this root')

        if not is_root:
            repositories = data.get('repositories', [])
            if isinstance(repositories, dict):
                repositories = repositories.values()
            paths = {r.get('url', '').rstrip('/').split('/')[-1]
                     for r in repositories if isinstance(r, dict) and r.get('type') == 'path'}
            for name in sorted(closure(data)):
                if packages[name][0] not in paths:
                    bad('path-repo', f'no path repository ../{packages[name][0]} for {name}')

    repository_packages = {
        repository['package']['name']: repository['package']
        for repository in roots[0][1].get('repositories', [])
        if repository.get('type') == 'package'
    }
    lock = load(ROOT / 'composer.lock')
    locked_packages = {package['name']: package for package in lock.get('packages', []) + lock.get('packages-dev', [])}
    for name in (*PACKMAN_CONSTRAINTS, *ALLOWED_PRERELEASE):
        source = repository_packages.get(name, {}).get('source', {})
        reference = source.get('reference', '')
        if source.get('type') != 'git' or not re.fullmatch(r'[0-9a-f]{40}', reference):
            violations.append(f'ROOT: ecosystem-pin: {name} must have an exact Git commit')
        elif locked_packages.get(name, {}).get('source') != source:
            violations.append(f'ROOT: ecosystem-pin: {name} lockfile differs from reviewed repository source')
        elif name in PACKMAN_CONSTRAINTS and (repository_packages.get(name, {}).get('version') != '0.1.0' or locked_packages.get(name, {}).get('version') != '0.1.0'):
            violations.append(f'ROOT: ecosystem-pin: {name} must be pinned to version 0.1.0')

    for line in violations:
        print(line)
    if violations:
        print(f'manifests FAIL: {len(violations)} violations', file=sys.stderr)
        return 1
    print(f'manifests OK: {len(roots)} roots')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
