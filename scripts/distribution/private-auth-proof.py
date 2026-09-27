#!/usr/bin/env python3
"""Run the D14-authorized private AzGuard proof in disposable storage only."""

from __future__ import annotations

import hashlib
import io
import json
import os
from pathlib import Path, PurePosixPath
import shutil
import subprocess
import tarfile
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
EXPORTER = ROOT / "scripts/distribution/export.py"
REF = "8c83864f02017134b325224c6c8be590a3971485"
AZGUARD = "axioma-studio/azguard-core"
AUTH = "corex/access"
VERSION = "0.1.0-p4.3"
AZGUARD_VERSION = "0.2.0-p4.5"
FIXED_TIME = (1980, 1, 1, 0, 0, 0)


class ProofError(RuntimeError):
    """A private artifact fixture crossed its finite boundary."""


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def run(*command: str, cwd: Path, environment: dict[str, str]) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=cwd, env=environment, text=True, capture_output=True)


def write_zip(destination: Path, entries: list[tuple[str, bytes]]) -> None:
    with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_STORED) as archive:
        for name, body in sorted(entries):
            entry = zipfile.ZipInfo(name, date_time=FIXED_TIME)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, body)


def azguard_archive(destination: Path) -> tuple[str, str, dict[str, str]]:
    response = subprocess.run(("gh", "api", f"repos/axiomasoft/azguard/tarball/{REF}"), capture_output=True)
    if response.returncode:
        raise ProofError("canonical private source is unavailable")
    entries: list[tuple[str, bytes]] = []
    composer_sha = ""
    requires: dict[str, str] = {}
    with tarfile.open(fileobj=io.BytesIO(response.stdout), mode="r:gz") as archive:
        for member in archive.getmembers():
            path = PurePosixPath(member.name)
            parts = path.parts[1:]
            if not member.isfile() or parts[:2] != ("packages", "core"):
                continue
            relative = PurePosixPath(*parts[2:])
            if not relative.parts or any(part in {"vendor", ".git", ".env", "auth.json"} for part in relative.parts):
                continue
            body = archive.extractfile(member)
            if body is None:
                raise ProofError("private archive member is unreadable")
            content = body.read()
            if relative.as_posix() == "composer.json":
                composer = json.loads(content)
                composer["version"] = AZGUARD_VERSION
                requires = composer["require"]
                content = (json.dumps(composer, indent=2, sort_keys=True) + "\n").encode()
                composer_sha = hashlib.sha256(content).hexdigest()
            entries.append((relative.as_posix(), content))
    if not composer_sha:
        raise ProofError("private package composer metadata is missing")
    write_zip(destination, entries)
    return digest(destination), composer_sha, requires


def auth_archive(destination: Path) -> str:
    entries: list[tuple[str, bytes]] = []
    package = ROOT / "packages/access"
    for path in package.rglob("*"):
        if not path.is_file() or any(part in {"vendor", ".git", ".env", "auth.json"} for part in path.relative_to(package).parts):
            continue
        entries.append((path.relative_to(package).as_posix(), path.read_bytes()))
    write_zip(destination, entries)
    return digest(destination)


def repository(root: Path) -> tuple[Path, dict]:
    stage = root / "repository"
    export = run("python3", str(EXPORTER), "--source-sha", "ea7d0dbdf36b122904abba91e124018c11b939ed", "--staging", str(stage), cwd=ROOT, environment=os.environ.copy())
    if export.returncode:
        raise ProofError("public lower-provider staging failed")
    metadata_path = stage / "packages.json"
    metadata = json.loads(metadata_path.read_text())
    for versions in metadata["packages"].values():
        for package in versions.values():
            package["dist"]["url"] = (stage / package["dist"]["url"]).as_uri()
    artifacts = stage / "artifacts"
    private_zip = artifacts / "azguard-core-private.zip"
    auth_zip = artifacts / "corex-auth.zip"
    private_sha, composer_sha, private_requires = azguard_archive(private_zip)
    auth_sha = auth_archive(auth_zip)
    metadata["packages"][AZGUARD] = {AZGUARD_VERSION: {"name": AZGUARD, "version": AZGUARD_VERSION, "require": private_requires, "dist": {"type": "zip", "url": private_zip.as_uri(), "reference": REF}, "source": {"type": "git", "url": "https://github.com/axiomasoft/azguard.git", "reference": REF}}}
    auth_composer = json.loads((ROOT / "packages/access/composer.json").read_text())
    auth_composer["version"] = VERSION
    auth_composer["require"]["corex/core"] = VERSION
    auth_composer["require"]["corex/modules"] = VERSION
    metadata["packages"][AUTH] = {VERSION: {"name": AUTH, "version": VERSION, "require": auth_composer["require"], "dist": {"type": "zip", "url": auth_zip.as_uri(), "reference": "local-p4.5"}}}
    metadata_path.write_text(json.dumps(metadata, indent=2, sort_keys=True) + "\n")
    return stage, {"azguard_archive_sha256": private_sha, "azguard_composer_sha256": composer_sha, "auth_archive_sha256": auth_sha, "metadata_sha256": digest(metadata_path)}


def install_role(root: Path, stage: Path, role: str) -> str:
    consumer = root / role
    consumer.mkdir()
    require = {AUTH: VERSION, "illuminate/contracts": "^12.0"}
    if role == "dependency":
        package = {"name": "example/auth-consumer", "version": "1.0.0", "require": {AUTH: VERSION}}
        package_zip = stage / "artifacts/example-auth-consumer.zip"
        write_zip(package_zip, [("composer.json", (json.dumps(package, indent=2) + "\n").encode())])
        metadata = json.loads((stage / "packages.json").read_text())
        metadata["packages"][package["name"]] = {"1.0.0": {**package, "dist": {"type": "zip", "url": package_zip.as_uri(), "reference": "p4.5"}}}
        (stage / "packages.json").write_text(json.dumps(metadata, indent=2, sort_keys=True) + "\n")
        require = {package["name"]: "1.0.0", "illuminate/contracts": "^12.0"}
    manifest = {"name": "corex/private-proof", "minimum-stability": "dev", "repositories": [{"type": "composer", "url": stage.as_uri()}], "require": require}
    (consumer / "composer.json").write_text(json.dumps(manifest, indent=2) + "\n")
    environment = {**os.environ, "COMPOSER_HOME": str(root / f"home-{role}"), "COMPOSER_CACHE_DIR": str(root / f"cache-{role}"), "COMPOSER_NO_INTERACTION": "1"}
    update = run("composer", "update", "--prefer-dist", "--no-progress", cwd=consumer, environment=environment)
    if update.returncode:
        raise ProofError(f"{role} cold update failed")
    lock_sha = digest(consumer / "composer.lock")
    shutil.rmtree(consumer / "vendor")
    shutil.rmtree(Path(environment["COMPOSER_HOME"]))
    shutil.rmtree(Path(environment["COMPOSER_CACHE_DIR"]))
    install = run("composer", "install", "--prefer-dist", "--no-progress", cwd=consumer, environment=environment)
    if install.returncode or (consumer / "vendor" / AZGUARD).is_symlink():
        raise ProofError(f"{role} fresh install failed")
    return lock_sha


def main() -> int:
    try:
        with tempfile.TemporaryDirectory(prefix="corex-private-auth-") as temporary:
            stage, evidence = repository(Path(temporary))
            evidence["auth_root_lock_sha256"] = install_role(Path(temporary), stage, "auth-root")
            evidence["auth_dependency_lock_sha256"] = install_role(Path(temporary), stage, "dependency")
            evidence["ref"] = REF
            print(json.dumps(evidence, sort_keys=True))
    except ProofError as error:
        print(f"private proof denied: {error}", file=os.sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
