"""Verify that a consumer installed precisely the candidate runtime closure."""
from __future__ import annotations

import hashlib
import json
from pathlib import Path

from ecosystem_repositories import is_corex_package, snapshot_repositories


class ConsumerError(RuntimeError):
    """A consumer escaped its declared dist inputs."""


def repositories(repository: Path, workspace: Path) -> list[dict]:
    return [
        {"type": "composer", "url": str(repository)},
        *snapshot_repositories(workspace),
        {
            "type": "composer",
            "url": "https://repo.packagist.org",
            "exclude": ["axiomasoft/corex", "axiomasoft/corex-*", "corex/*", "axioma-studio/*"],
        },
        {"packagist.org": False},
    ]


def load_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def dist_closure(declared: list[str], metadata: dict) -> dict:
    result = {}
    pending = list(declared)
    while pending:
        name = pending.pop()
        if name in result or not is_corex_package(name):
            continue
        versions = metadata.get(name, {})
        if len(versions) != 1:
            raise ConsumerError(f"candidate requires one version of {name}")
        row = next(iter(versions.values()))
        result[name] = row
        pending.extend(row.get("require", {}))
    return result


def assert_dist_install(consumer: Path, repository: Path, declared: list[str], workspace: Path) -> dict:
    lock_path = consumer / "composer.lock"
    vendor = consumer / "vendor"
    composer = vendor / "composer"
    lock = load_json(lock_path)
    installed = load_json(composer / "installed.json")
    expected = dist_closure(declared, load_json(repository / "packages.json")["packages"])
    locked = {row["name"]: row for row in lock["packages"] + lock.get("packages-dev", []) if is_corex_package(row["name"])}
    actual = {row["name"]: row for row in installed["packages"] if is_corex_package(row["name"])}
    if set(locked) != set(expected) or set(actual) != set(expected):
        raise ConsumerError(f"expected CoreX closure {sorted(expected)}, locked {sorted(locked)}, installed {sorted(actual)}")
    for name, package in expected.items():
        for row in (actual[name], locked[name]):
            if row["version"] != package["version"] or row.get("dist") != package["dist"] or "source" in row:
                raise ConsumerError(f"{name} did not resolve from the candidate ZIP: {row.get('dist')}")
        path = composer / actual[name]["install-path"]
        if not path.is_dir() or not path.resolve().is_relative_to(vendor.resolve()):
            raise ConsumerError(f"{name} installation escaped the consumer vendor")
    generated = [lock_path, *composer.glob("autoload*.php")]
    workspace_path = str(workspace.resolve()).encode()
    for file in generated:
        if workspace_path in file.read_bytes():
            raise ConsumerError(f"workspace path leaked in {file.name}")
    return {
        "installed": sorted(f"{name}:{row['version']}" for name, row in actual.items()),
        "lock_sha256": hashlib.sha256(lock_path.read_bytes()).hexdigest(),
    }
