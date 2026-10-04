#!/usr/bin/env python3
"""Exercise sealed CoreX candidates through a disposable Composer consumer."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[2]
EXPORTER = ROOT / "scripts/distribution/export.py"
VERSION = "0.1.0-p4.3"
PACKAGES = ("axiomasoft/corex", "axiomasoft/corex", "axiomasoft/corex-tasks", "axiomasoft/corex-tenancy")
POLICY = ROOT / "scripts/distribution/export-policy.json"


class ProofError(RuntimeError):
    """The staged consumer did not meet the public-distribution boundary."""


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def command(*parts: str, cwd: Path, environment: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(parts, cwd=cwd, env=environment, text=True, capture_output=True)


def build_repository(root: Path) -> dict:
    staging = root / "staging"
    source_ref = json.loads(POLICY.read_text(encoding="utf-8"))["source_ref"]
    result = command("python3", str(EXPORTER), "--source-sha", source_ref, "--staging", str(staging), cwd=ROOT, environment=os.environ.copy())
    if result.returncode:
        raise ProofError(result.stderr.strip() or "export failed")
    metadata_path = staging / "packages.json"
    metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
    for versions in metadata["packages"].values():
        for package in versions.values():
            package["dist"]["url"] = (staging / package["dist"]["url"]).as_uri()

    metadata_path.write_text(json.dumps(metadata, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return {"staging": staging, "metadata_sha256": sha256(metadata_path), "export_map": json.loads((staging / "export-map.json").read_text(encoding="utf-8"))}


def consumer_manifest(package: str, laravel: str, repository: Path) -> dict:
    return {
        "name": "corex/distribution-proof-consumer",
        "type": "project",
        "minimum-stability": "dev",
        "prefer-stable": True,
        "repositories": [{"type": "composer", "url": repository.as_uri()}],
        "require": {package: VERSION, "illuminate/contracts": f"^{laravel}.0"},
        "config": {"allow-plugins": {"pestphp/pest-plugin": True}},
    }


def prove_cell(root: Path, repository: Path, package: str, laravel: str) -> dict:
    consumer = root / f"{package.replace('/', '-')}-l{laravel}"
    consumer.mkdir()
    (consumer / "composer.json").write_text(json.dumps(consumer_manifest(package, laravel, repository), indent=2) + "\n", encoding="utf-8")
    home = root / f"home-{package.replace('/', '-')}-l{laravel}"
    cache = root / f"cache-{package.replace('/', '-')}-l{laravel}"
    environment = {**os.environ, "COMPOSER_HOME": str(home), "COMPOSER_CACHE_DIR": str(cache), "COMPOSER_NO_INTERACTION": "1"}
    update = command("composer", "update", "--prefer-dist", "--no-progress", cwd=consumer, environment=environment)
    if update.returncode:
        raise ProofError(f"{package}/L{laravel} update failed: {update.stderr.splitlines()[-1:]}")
    platform = command("composer", "check-platform-reqs", "--no-ansi", cwd=consumer, environment=environment)
    if platform.returncode:
        raise ProofError(f"{package}/L{laravel} platform failed")
    lock_sha = sha256(consumer / "composer.lock")
    shutil.rmtree(consumer / "vendor")
    shutil.rmtree(home)
    shutil.rmtree(cache)
    home.mkdir()
    cache.mkdir()
    install = command("composer", "install", "--prefer-dist", "--no-progress", cwd=consumer, environment=environment)
    if install.returncode:
        raise ProofError(f"{package}/L{laravel} fresh install failed: {install.stderr.splitlines()[-1:]}")
    installed = consumer / "vendor" / package
    if not (installed / "composer.json").is_file() or ROOT in installed.resolve().parents:
        raise ProofError(f"{package}/L{laravel} is not an installed distribution source")
    if any(path.is_symlink() for path in installed.rglob("*")):
        raise ProofError(f"{package}/L{laravel} installed source contains a symlink")
    return {"package": package, "laravel": laravel, "lock_sha256": lock_sha, "installed_source": str(installed.relative_to(root)), "platform": "passed", "fresh_install": "passed"}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--laravel", choices=("12", "13"), action="append", default=[])
    parser.add_argument("--package", choices=PACKAGES, action="append", default=[])
    args = parser.parse_args()
    laravels = args.laravel or ["12", "13"]
    packages = args.package or list(PACKAGES)
    try:
        with tempfile.TemporaryDirectory(prefix="corex-pristine-") as temporary:
            root = Path(temporary)
            repository = build_repository(root)
            cells = [prove_cell(root, repository["staging"], package, laravel) for package in packages for laravel in laravels]
            print(json.dumps({"php": subprocess.run(("php", "-r", "echo PHP_VERSION;"), text=True, capture_output=True).stdout, "composer": subprocess.run(("composer", "--version"), text=True, capture_output=True).stdout.strip(), "metadata_sha256": repository["metadata_sha256"], "source_ref": repository["export_map"]["source_ref"], "cells": cells}, sort_keys=True))
    except ProofError as error:
        print(f"pristine proof denied: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
