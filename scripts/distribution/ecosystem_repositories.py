"""Portable, reviewed owner snapshots for isolated CoreX consumer installs."""
from __future__ import annotations

import json
import re
from pathlib import Path
from urllib.parse import urlparse


def is_corex_package(name: str) -> bool:
    return name == "axiomasoft/corex" or name.startswith(("axiomasoft/corex-", "corex/"))


def snapshot_repositories(root: Path) -> list[dict]:
    """Copy only pinned external package metadata; never inherit workspace paths."""
    manifest = json.loads((root / "composer.json").read_text(encoding="utf-8"))
    snapshots = []
    repositories = manifest.get("repositories", [])
    if isinstance(repositories, dict):
        repositories = repositories.values()
    for repository in repositories:
        if not isinstance(repository, dict) or repository.get("type") != "package":
            continue
        package = repository.get("package", {})
        name = package.get("name", "")
        if is_corex_package(name) or name.startswith("flex/"):
            raise ValueError(f"consumer candidate cannot be shadowed by a root snapshot: {name}")
        source = package.get("source", {})
        url = urlparse(source.get("url", ""))
        if (source.get("type") != "git" or url.scheme != "https" or not url.hostname
                or url.username is not None or url.password is not None
                or not re.fullmatch(r"[0-9a-f]{40}", source.get("reference", ""))):
            raise ValueError(f"external snapshot needs a portable pinned Git source: {name}")
        snapshots.append(repository)
    return snapshots


def reviewed_snapshot_names(root: Path, repositories: list[dict]) -> set[str]:
    """Admit a dev dependency only when effective metadata equals the reviewed lock."""
    snapshots = snapshot_repositories(root)
    if not snapshots:
        return set()
    locked = json.loads((root / "composer.lock").read_text(encoding="utf-8"))
    packages = {p["name"]: p for p in [*locked.get("packages", []), *locked.get("packages-dev", [])]}
    admitted = set()
    fields = ("version", "type", "source", "require", "conflict", "replace", "provide", "autoload", "extra")
    for snapshot in snapshots:
        package = snapshot["package"]
        name = package["name"]
        candidates = [r["package"] for r in repositories if isinstance(r, dict)
                      and r.get("type") == "package" and isinstance(r.get("package"), dict)
                      and r["package"].get("name") == name]
        if not candidates or candidates[0] != package:
            raise ValueError(f"effective snapshot differs from root metadata: {name}")
        expected = packages.get(name)
        if expected is None or any(expected.get(field) != package.get(field) for field in fields):
            raise ValueError(f"external snapshot differs from reviewed root lock: {name}")
        admitted.add(name)
    return admitted


def with_snapshot_requirements(manifest: dict, root: Path, bindings: list[dict]) -> dict:
    """Expose dev stability only for reviewed owners reachable from this source root.

    Composer ignores dependency-level stability flags. Adding reachable snapshots to
    this generated root preserves their declared constraints without admitting unrelated
    dev packages or installing optional owners from the monorepo's complete snapshot set.
    """
    effective = json.loads(json.dumps(manifest))
    snapshots = {r["package"]["name"]: r["package"] for r in snapshot_repositories(root)}
    reviewed = reviewed_snapshot_names(root, effective.get("repositories", []))
    local = {}
    for binding in bindings:
        package = json.loads((root / binding["manifest"]).read_text(encoding="utf-8"))
        local.setdefault(package["name"], package)
    pending = list(effective.get("require", {})) + list(effective.get("require-dev", {}))
    visited = set()
    while pending:
        name = pending.pop()
        if name in visited:
            continue
        visited.add(name)
        package = snapshots.get(name) if name in reviewed else local.get(name)
        if package is None:
            continue
        if name in reviewed:
            if re.search(r"(?:^dev-|-dev$|@(?:dev|alpha|beta|RC)\b|-(?:alpha|beta|RC))", str(package.get("version", "")), re.IGNORECASE):
                section = "require-dev" if name in effective.get("require-dev", {}) else "require"
                requirements = effective.setdefault(section, {})
                constraint = requirements.get(name, package["version"])
                # Retain version bounds; only this exact reviewed owner gains dev stability.
                requirements[name] = re.sub(r"@(stable|RC|beta|alpha|dev)\b", "", constraint) + "@dev"
        pending.extend(package.get("require", {}))
    return effective


def reviewed_toolchain_arguments(root: Path, manifest: dict) -> list[str]:
    lock = json.loads((root / "composer.lock").read_text(encoding="utf-8"))
    packages = {p["name"]: p for p in [*lock.get("packages", []), *lock.get("packages-dev", [])]}
    tools = json.loads((Path(__file__).resolve().parents[1] / "composer-toolchain.json").read_text(encoding="utf-8"))
    pending = list(manifest.get("require", {})) + list(manifest.get("require-dev", {}))
    reachable = set()
    while pending:
        name = pending.pop()
        if name in reachable:
            continue
        reachable.add(name)
        pending.extend(packages.get(name, {}).get("require", {}))
    arguments = []
    for tool in tools:
        if tool in reachable:
            if tool not in packages:
                raise ValueError(f"reviewed root lock has no toolchain version: {tool}")
            arguments.extend(["--with", f"{tool}:{packages[tool]['version']}"])
    return arguments
