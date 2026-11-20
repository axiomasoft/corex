#!/usr/bin/env python3
"""Discover and verify previously published immutable Composer archives."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import quote

from consumer_repository import load_json
from release_candidate import CandidateError
import verify_extension_fixtures as extensions

ROOT = Path(__file__).resolve().parents[2]
BASELINES_PATH = ROOT / "build/distribution/release-baselines.json"
CACHE = Path(os.environ.get("COREX_UPGRADE_CACHE", "/tmp/corex-upgrade-baselines"))
COMMIT_RE = re.compile(r"^[0-9a-f]{40}$")


class UpgradeError(RuntimeError):
    """The immutable baseline descriptor or archive failed verification."""


def version_key(tag: str) -> tuple:
    raw = tag[1:] if tag.startswith("v") else tag
    number, _, pre = raw.partition("-")
    parts = tuple(int(part) for part in number.split(".") if part != "")
    return (parts, pre == "", pre)


def release_version(tag: str) -> str:
    return tag[1:] if tag.startswith("v") else tag


def assert_candidate(actual: str, expected: str) -> None:
    if actual != expected:
        raise UpgradeError("stale candidate")


def assert_baseline_digest(actual: str, expected: str) -> None:
    if actual != expected:
        raise UpgradeError("wrong baseline digest")


def assert_baseline_row(row: dict, source_commit: str = "") -> None:
    classification = row.get("classification")
    if classification == "new-package":
        absence = row.get("absence")
        if row.get("prior_version") or row.get("archive_sha256") or not isinstance(absence, dict) or absence.get("observed") is not True:
            raise UpgradeError("missing remote absence")
        if row.get("reason") != "not-applicable/new-package":
            raise UpgradeError("missing remote absence")
        return
    if classification != "upgraded":
        raise UpgradeError("generic skip")
    ref = row.get("immutable_ref")
    channel = row.get("channel")
    if (not isinstance(ref, str) or not ref or ref == source_commit or COMMIT_RE.fullmatch(ref)
            or channel == "monorepo-tag"):
        raise UpgradeError("monorepo tag substitution")
    if channel != "github-release" or not isinstance(row.get("archive_sha256"), str):
        raise UpgradeError("wrong baseline digest")


def select_latest(releases: list[dict]) -> dict | None:
    usable = [release for release in releases if release.get("asset")]
    if not usable:
        return None
    return max(usable, key=lambda release: version_key(str(release["tag"])))


def cohort_packages(candidate_path: Path) -> list[dict]:
    return load_json(extensions.candidate_directory(candidate_path) / "candidate.json")["packages"]


def gh_json(path: str) -> tuple[int, dict | list | None, str]:
    completed = subprocess.run(["gh", "api", path], text=True, capture_output=True, check=False)
    if completed.returncode:
        detail = (completed.stderr or completed.stdout).strip()
        if "404" in detail or "Not Found" in detail:
            return 404, None, detail
        raise UpgradeError(f"remote baseline query failed: {detail}")
    return 200, json.loads(completed.stdout), ""


def discover_package(package: dict) -> dict:
    mirror = package["mirror"]
    status, payload, detail = gh_json(f"repos/{mirror}/releases?per_page=20")
    if status == 404 or not isinstance(payload, list):
        return {
            "package": package["name"],
            "classification": "new-package",
            "prior_version": None,
            "channel": "github-release",
            "immutable_ref": None,
            "archive_sha256": None,
            "archive_url": None,
            "mirror": mirror,
            "reason": "not-applicable/new-package",
            "absence": {"observed": True, "status": status, "mirror": mirror, "detail": detail[:240]},
        }
    releases = []
    for release in payload:
        if release.get("draft"):
            continue
        assets = [asset for asset in release.get("assets", []) if str(asset.get("name", "")).endswith(".zip")]
        if not assets:
            continue
        releases.append({
            "tag": release["tag_name"],
            "asset": assets[0]["name"],
            "api": assets[0]["url"],
            "url": assets[0].get("browser_download_url"),
        })
    archives = []
    for release in releases:
        version = release_version(str(release["tag"]))
        archive = download_asset(mirror, str(release["tag"]), str(release["api"]), CACHE / f"{package['name'].replace('/', '-')}-{version}.zip")
        archives.append({
            "version": version,
            "immutable_ref": str(release["tag"]),
            "archive_sha256": hashlib.sha256(archive.read_bytes()).hexdigest(),
            "archive_url": release.get("url"),
            "asset_api": release["api"],
            "mirror": mirror,
        })
    latest = select_latest(releases)
    if latest is None:
        return {
            "package": package["name"],
            "classification": "new-package",
            "prior_version": None,
            "channel": "github-release",
            "immutable_ref": None,
            "archive_sha256": None,
            "archive_url": None,
            "mirror": mirror,
            "reason": "not-applicable/new-package",
            "absence": {"observed": True, "status": 200, "mirror": mirror, "asset_count": 0},
        }
    selected = next(archive for archive in archives if archive["immutable_ref"] == latest["tag"])
    return {
        "package": package["name"],
        "classification": "upgraded",
        "prior_version": selected["version"],
        "channel": "github-release",
        "immutable_ref": selected["immutable_ref"],
        "archive_sha256": selected["archive_sha256"],
        "archive_url": selected["archive_url"],
        "asset_api": selected["asset_api"],
        "mirror": mirror,
        "reason": "latest published GitHub release asset",
        "absence": None,
        "supporting_archives": [archive for archive in archives if archive["immutable_ref"] != selected["immutable_ref"]],
    }


def download_asset(mirror: str, tag: str, api_url: str, destination: Path) -> Path:
    destination.parent.mkdir(parents=True, exist_ok=True)
    if destination.is_file():
        return destination
    completed = subprocess.run(
        ["gh", "api", api_url, "-H", "Accept: application/octet-stream"],
        capture_output=True,
        check=False,
    )
    if completed.returncode or not completed.stdout:
        raise UpgradeError(f"remote baseline download failed: {mirror}@{tag}")
    destination.write_bytes(completed.stdout)
    return destination


def discover(path: Path = BASELINES_PATH, *, candidate_path: Path) -> dict:
    binding = extensions.candidate_binding(candidate_path)
    rows = [discover_package(package) for package in cohort_packages(candidate_path)]
    for row in rows:
        assert_baseline_row(row)
    document = {
        "schema_version": "corex-release-baselines/1",
        **binding,
        "observed_at": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "packages": rows,
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
    return document


def zip_metadata(archive: Path) -> dict:
    with zipfile.ZipFile(archive) as bundle:
        composer = json.loads(bundle.read("composer.json"))
    composer["version"] = composer.get("version")
    return composer


def stage_archive(package: str, archive_row: dict, artifacts: Path, packages: dict[str, dict]) -> None:
    version = archive_row["version"] if "version" in archive_row else archive_row["prior_version"]
    archive = CACHE / f"{package.replace('/', '-')}-{version}.zip"
    if not archive.is_file():
        download_asset(archive_row["mirror"], archive_row["immutable_ref"], archive_row["asset_api"], archive)
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    assert_baseline_digest(digest, archive_row["archive_sha256"])
    staged = artifacts / archive.name
    shutil.copyfile(archive, staged)
    composer = zip_metadata(staged)
    if composer.get("name") != package:
        raise UpgradeError(f"baseline archive identity mismatch: {package}")
    composer["version"] = version
    composer["dist"] = {"type": "zip", "url": staged.resolve().as_uri()}
    packages.setdefault(package, {})[version] = composer


def stage_baseline_repository(baselines: list[dict], repository: Path) -> None:
    artifacts = repository / "artifacts"
    artifacts.mkdir(parents=True, exist_ok=True)
    packages: dict[str, dict] = {}
    for row in baselines:
        if row["classification"] != "upgraded":
            continue
        stage_archive(row["package"], row, artifacts, packages)
        for archive in row.get("supporting_archives") or []:
            stage_archive(row["package"], archive, artifacts, packages)
    (repository / "packages.json").write_text(json.dumps({"packages": packages}) + "\n", encoding="utf-8")


def verify(path: Path, candidate: Path) -> dict:
    document = load_json(path)
    binding = extensions.candidate_binding(candidate)
    assert_candidate(document.get("candidate_sha256"), binding["candidate_sha256"])
    if document.get("schema_version") != "corex-release-baselines/1":
        raise UpgradeError("wrong baseline descriptor schema")
    rows = document["packages"]
    expected = [row["name"] for row in cohort_packages(candidate)]
    if [row["package"] for row in rows] != expected:
        raise UpgradeError("baseline descriptor differs from candidate cohort")
    for row in rows:
        assert_baseline_row(row, binding["source_commit"])
    with tempfile.TemporaryDirectory(prefix="corex-baseline-archives-") as temporary:
        stage_baseline_repository(rows, Path(temporary) / "repository")
    return {"result": "PASS", **binding, "packages": len(rows)}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=("discover", "verify"))
    parser.add_argument("--baselines", type=Path, default=BASELINES_PATH)
    parser.add_argument("--candidate", type=Path, required=True)
    args = parser.parse_args()
    try:
        if args.command == "discover":
            document = discover(args.baselines, candidate_path=args.candidate)
            report = {"result": "PINNED", "packages": len(document["packages"]), "baselines": str(args.baselines)}
        else:
            report = verify(args.baselines, args.candidate)
    except (CandidateError, UpgradeError, OSError, KeyError, ValueError) as error:
        print(f"baseline archives denied: {error}", file=sys.stderr)
        return 1
    print(json.dumps(report, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
