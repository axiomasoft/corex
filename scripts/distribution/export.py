#!/usr/bin/env python3
"""Create deterministic, local-only CoreX package distribution candidates.

Historical four-package export uses export-policy.json. The remaining public cohort
(P9) uses scripts/distribution/remaining-cohort-export.py with
remaining-cohort-policy.json and the frozen delivery/remaining-cohort-20260915/candidate.json.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import subprocess
import sys
import tempfile
import zipfile

ROOT = Path(__file__).resolve().parents[2]
POLICY_PATH = Path(__file__).with_name("export-policy.json")
FIXED_TIME = (1980, 1, 1, 0, 0, 0)


class ExportError(RuntimeError):
    """A source or artifact violates the local distribution contract."""


def digest(value: bytes) -> str:
    return hashlib.sha256(value).hexdigest()


def git(*args: str) -> bytes:
    result = subprocess.run(("git", *args), cwd=ROOT, capture_output=True)
    if result.returncode:
        raise ExportError(result.stderr.decode("utf-8", "replace").strip() or "git command failed")
    return result.stdout


def policy() -> dict:
    value = json.loads(POLICY_PATH.read_text(encoding="utf-8"))
    if set(value.get("packages", {})) != {"corex/core", "corex/modules", "corex/tasks", "corex/tenancy"}:
        raise ExportError("policy allowlist is invalid")
    return value


def include(path: PurePosixPath, value: dict) -> bool:
    if any(segment in value["forbidden_segments"] for segment in path.parts):
        return False
    item = path.as_posix()
    return any(item == rule or item.startswith(rule) for rule in value["include"])


def package_files(ref: str, root: str, value: dict) -> list[tuple[str, str]]:
    rows = git("ls-tree", "-r", "-z", ref, "--", root).split(b"\0")
    files: list[tuple[str, str]] = []
    for row in rows:
        if not row:
            continue
        metadata, raw_path = row.split(b"\t", 1)
        mode, _kind, object_id = metadata.decode().split()
        full = raw_path.decode()
        relative = PurePosixPath(full).relative_to(root)
        if mode == "120000":
            raise ExportError(f"symlink denied: {full}")
        if mode not in {"100644", "100755"}:
            raise ExportError(f"unsupported mode: {full}")
        if include(relative, value):
            files.append((relative.as_posix(), object_id))
    if not any(path == "composer.json" for path, _ in files):
        raise ExportError(f"composer.json missing: {root}")
    return sorted(files)


def patched_composer(ref: str, root: str, version: str, names: set[str]) -> bytes:
    value = json.loads(git("show", f"{ref}:{root}/composer.json"))
    requires = value.get("require", {})
    for name in names & set(requires):
        requires[name] = version
    value["require"] = dict(sorted(requires.items()))
    value["version"] = version
    value.pop("repositories", None)
    return (json.dumps(value, ensure_ascii=False, indent=2, sort_keys=True) + "\n").encode()


def archive(path: Path, ref: str, root: str, value: dict, names: set[str]) -> tuple[str, str, str]:
    files = package_files(ref, root, value)
    package_has_license = any(relative == "LICENSE" for relative, _ in files)
    license_body = (
        git("cat-file", "blob", next(object_id for relative, object_id in files if relative == "LICENSE"))
        if package_has_license
        else git("show", f"{ref}:LICENSE")
    )
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_STORED) as output:
        for relative, object_id in files:
            body = patched_composer(ref, root, value["version"], names) if relative == "composer.json" else git("cat-file", "blob", object_id)
            info = zipfile.ZipInfo(relative, date_time=FIXED_TIME)
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            output.writestr(info, body)
        if not package_has_license:
            info = zipfile.ZipInfo("LICENSE", date_time=FIXED_TIME)
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            output.writestr(info, license_body)
    raw = path.read_bytes()
    return digest(raw), hashlib.sha1(raw).hexdigest(), digest(license_body)


def candidate(value: dict) -> tuple[dict[str, str], set[str]]:
    known = set(policy()["packages"])
    packages = value.get("packages")
    cohort = value.get("cohort", packages)

    if not isinstance(packages, dict) or not packages:
        raise ExportError("candidate packages are required")
    if not isinstance(cohort, list) or not cohort:
        raise ExportError("candidate cohort is required")
    if not set(packages).issubset(known) or not set(cohort).issubset(known):
        raise ExportError("candidate includes an unapproved CoreX package")
    if not set(packages).issubset(cohort):
        raise ExportError("candidate cohort must include exported packages")
    if not isinstance(value.get("version"), str) or not value["version"]:
        raise ExportError("candidate version is required")
    if not isinstance(value.get("include"), list) or not isinstance(value.get("forbidden_segments"), list):
        raise ExportError("candidate archive rules are required")

    return packages, set(cohort)


def export_candidate(ref: str, staging: Path, value: dict) -> dict:
    packages_to_export, names = candidate(value)
    git("cat-file", "-e", f"{ref}^{{commit}}")
    if staging.exists():
        raise ExportError(f"staging already exists: {staging}")
    artifacts = staging / "artifacts"
    artifacts.mkdir(parents=True)
    packages: dict[str, dict] = {}
    trees: dict[str, str] = {}
    for name, root in sorted(packages_to_export.items()):
        filename = name.replace("/", "-") + ".zip"
        archive_sha, archive_sha1, license_sha = archive(artifacts / filename, ref, root, value, names)
        composer = json.loads(git("show", f"{ref}:{root}/composer.json"))
        requires = composer.get("require", {})
        for dependency in names & set(requires):
            requires[dependency] = value["version"]
        trees[name] = git("rev-parse", f"{ref}:{root}").decode().strip()
        packages[name] = {"name": name, "version": value["version"], "require": dict(sorted(requires.items())), "source": {"type": "git", "url": f"https://github.com/axiomasoft/{name.replace('/', '-')}.git", "reference": ref}, "dist": {"type": "zip", "url": f"artifacts/{filename}", "reference": ref, "shasum": archive_sha1}, "extra": {"corex_local_candidate": True}, "archive_sha256": archive_sha, "license_sha256": license_sha}
    metadata = {"packages": {name: {value["version"]: item} for name, item in packages.items()}}
    metadata_path = staging / "packages.json"
    metadata_path.write_text(json.dumps(metadata, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    manifest = {"source_ref": ref, "policy_sha256": digest(POLICY_PATH.read_bytes()), "exporter_sha256": digest(Path(__file__).read_bytes()), "package_trees": trees, "metadata_sha256": digest(metadata_path.read_bytes()), "packages": packages}
    (staging / "export-map.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    return manifest


def export(ref: str, staging: Path) -> dict:
    value = policy()
    if ref != value["source_ref"]:
        raise ExportError("source SHA is not the approved committed candidate")

    return export_candidate(ref, staging, value)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source-sha")
    parser.add_argument("--staging", type=Path)
    parser.add_argument("--candidate", type=Path)
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()
    try:
        if args.check:
            with tempfile.TemporaryDirectory() as first, tempfile.TemporaryDirectory() as second:
                left = export(policy()["source_ref"], Path(first) / "candidate")
                right = export(policy()["source_ref"], Path(second) / "candidate")
                if left != right:
                    raise ExportError("independent builds differ")
                print(json.dumps(left, sort_keys=True))
        elif args.source_sha and args.staging:
            if args.candidate is None:
                manifest = export(args.source_sha, args.staging)
            else:
                value = json.loads(args.candidate.read_text(encoding="utf-8"))
                if value.get("source_ref") != args.source_sha:
                    raise ExportError("candidate source SHA does not match --source-sha")
                manifest = export_candidate(args.source_sha, args.staging, value)
            print(json.dumps(manifest, ensure_ascii=False, sort_keys=True))
        else:
            parser.error("use --check or both --source-sha and --staging, optionally with --candidate")
    except ExportError as error:
        print(f"export denied: {error}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
