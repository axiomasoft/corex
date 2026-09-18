#!/usr/bin/env python3
"""Prepare read-only private-read-packet/1 for P9.6 G-PRIVATE dispatch."""
from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REPO_DELIVERY = ROOT / 'delivery/remaining-cohort-20260915'
PLAN = ROOT / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
PRIVATE_SCRIPT = Path(__file__).resolve()


class PrivatePacketError(RuntimeError):
    """Private read packet construction violated the remaining-cohort contract."""


def sha256_path(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding='utf-8'))


def write_json(path: Path, value: object) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def build_private_read_packet() -> dict:
    candidate_path = REPO_DELIVERY / 'candidate.json'
    profile_path = REPO_DELIVERY / 'profile-plan.json'
    for path in (candidate_path, profile_path):
        if not path.is_file():
            raise PrivatePacketError(f'missing prerequisite artifact: {path}')

    candidate = load_json(candidate_path)
    profile_plan = load_json(profile_path)
    auth_profile = next(
        (row for row in profile_plan.get('private_profiles', []) if row.get('profile_id') == 'optional-auth-azguard'),
        None,
    )
    if auth_profile is None:
        raise PrivatePacketError('optional-auth-azguard profile missing from profile-plan')

    return {
        'schema_version': 'private-read-packet/1',
        'profile_id': auth_profile['profile_id'],
        'package': auth_profile['package'],
        'upstream': auth_profile['upstream'],
        'candidate_sha256': sha256_path(candidate_path),
        'build_attestation_sha256': sha256_path(REPO_DELIVERY / 'build/build-attestation.json'),
        'read_scope': 'P9.6:private-read-packet/1:exact-azguard-ref-read-install',
        'channel': auth_profile.get('channel', 'private'),
        'supported': auth_profile.get('supported', {}),
        'unsupported': auth_profile.get('unsupported', []),
        'operations': [
            {
                'operation_id': 'private-read-azguard-source',
                'kind': 'read',
                'repository': auth_profile['upstream'],
                'scope': 'source+license+dist-by-ref',
                'write': False,
            },
            {
                'operation_id': 'private-install-auth-direct',
                'kind': 'install',
                'package': auth_profile['package'],
                'scope': 'direct-cold-install',
                'write': False,
            },
            {
                'operation_id': 'private-install-auth-transitive',
                'kind': 'install',
                'package': 'example/auth-consumer',
                'scope': 'transitive-consumer-install',
                'write': False,
            },
        ],
        'protected': True,
        'grant_required': 'G-PRIVATE',
        'recipe_commits': {
            'remaining-cohort-private.py': sha256_path(PRIVATE_SCRIPT),
        },
        'note': 'Read-only packet for owner review; P9.6 executes actual private read/install with grant',
    }


def verify_private_read_packet(packet_path: Path) -> int:
    packet = load_json(packet_path)
    if packet.get('schema_version') != 'private-read-packet/1':
        return 1
    if not packet.get('protected'):
        return 1
    if packet.get('grant_required') != 'G-PRIVATE':
        return 1
    for operation in packet.get('operations', []):
        if operation.get('write'):
            return 1
    candidate_sha = sha256_path(REPO_DELIVERY / 'candidate.json')
    if packet.get('candidate_sha256') != candidate_sha:
        return 1
    return 0


def verify_guard_refuses_write() -> int:
    packet = build_private_read_packet()
    if not packet.get('protected'):
        return 1
    if any(operation.get('write') for operation in packet.get('operations', [])):
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--build', action='store_true', help='Write private-read-packet.json')
    parser.add_argument('--verify', type=Path, help='Verify an existing private-read-packet.json')
    parser.add_argument('--verify-guard', action='store_true', help='Verify read-only guard semantics')
    args = parser.parse_args()

    if args.verify:
        return verify_private_read_packet(args.verify)

    if args.verify_guard:
        return verify_guard_refuses_write()

    if args.build:
        packet = build_private_read_packet()
        output = REPO_DELIVERY / 'private-read-packet.json'
        write_json(output, packet)
        print(json.dumps({'result': 'PASS', 'private_read_packet_sha256': sha256_path(output)}))
        return 0

    parser.print_help()
    return 2


if __name__ == '__main__':
    raise SystemExit(main())
