#!/usr/bin/env python3
"""Reproduce and verify the local P4.1 metadata-only source candidates, offline."""
from __future__ import annotations

import argparse
import difflib
import hashlib
import io
import json
from pathlib import Path, PurePosixPath
import stat
import subprocess
import tarfile
import zipfile

ROOT = Path(__file__).resolve().parent
REPOSITORY = ROOT.parents[3]
PLAN = ROOT.parents[1]
OWNER_RECORD = "decisions/D9-p41-local-source-qualification.md"
PACKAGES = {
    "tenancy": {
        "upstream": "stancl/tenancy",
        "commit": "76e5f9655924271c6c9da54e2d4ccb82c6f71ea0",
        "tree": "af343237206d369917047b59db2d169ede66354f",
        "tar_sha256": "704df0a26dc837a989ebe17fa93ab3659063a0d75dc6786947464dca126db85a",
        "composer_sha256": "1d95150c3a3dc36c4d74bcfe31fff028ca3128d42fc6fd9a2f149f7e5292cf59",
        "license_sha256": "02c6adcac42b9bdffe0502ba5f6c24f799a383911b3f9667d064571a9672599f",
        "name": "axiomasoft/stancl-tenancy",
        "version": "4.0.0-alpha1",
    },
    "jobpipeline": {
        "upstream": "stancl/jobpipeline",
        "commit": "07dc54a49cf9e2df1238ee4a347af333260519a6",
        "tree": "23e3d1408f5960ba195549097a5a4184e422506c",
        "tar_sha256": "62bb5ee3d1e1eda835ac28eb62fae3ef48e0940ce420f79c93fdeb1ae8258d73",
        "composer_sha256": "203c322584e57f648c33ad3f15769e34d22d4ca7c09ae21b718893a97d2fd947",
        "license_sha256": "c269f5086fffeb38c5633e946349b78d5754603312fa06054c675af3e153142e",
        "name": "axiomasoft/stancl-jobpipeline",
        "version": "2.0.0-alpha1",
    },
}


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ValueError(message)


def json_bytes(value: object) -> bytes:
    return (json.dumps(value, ensure_ascii=False, indent=4) + "\n").encode()


def git_object(kind: str, data: bytes) -> bytes:
    return hashlib.sha1(kind.encode() + b" " + str(len(data)).encode() + b"\0" + data).digest()


def git_tree(files: dict[str, tuple[bytes, int]]) -> str:
    root: dict = {}
    for name, (content, mode) in files.items():
        parts = name.split("/")
        node = root
        for part in parts[:-1]:
            node = node.setdefault(part, {})
        node[parts[-1]] = (content, mode)

    def digest(node: dict) -> bytes:
        rows = []
        for name, member in node.items():
            if isinstance(member, dict):
                mode, object_id, sort_key = b"40000", digest(member), (name + "/").encode()
            else:
                content, executable_mode = member
                mode = b"100755" if executable_mode == 0o755 else b"100644"
                object_id, sort_key = git_object("blob", content), name.encode()
            rows.append((sort_key, mode + b" " + name.encode() + b"\0" + object_id))
        return git_object("tree", b"".join(row for _, row in sorted(rows)))

    return digest(root).hex()


def read_source(label: str, record: dict) -> dict[str, tuple[bytes, int]]:
    raw = (ROOT / f"{label}-upstream.tar").read_bytes()
    require(sha256(raw) == record["tar_sha256"], f"{label}: upstream TAR digest mismatch")
    files = {}
    with tarfile.open(fileobj=io.BytesIO(raw), mode="r:") as archive:
        for member in archive:
            path = PurePosixPath(member.name)
            require(member.isfile() and member.mode in (0o644, 0o755), member.name)
            require(not path.is_absolute() and ".." not in path.parts and "\\" not in member.name, "unsafe archive path")
            require(member.name not in files, f"duplicate path {member.name}")
            stream = archive.extractfile(member)
            require(stream is not None, "missing archive stream")
            files[member.name] = (stream.read(), member.mode)
    require(git_tree(files) == record["tree"], f"{label}: full upstream Git tree mismatch")
    require(sha256(files["composer.json"][0]) == record["composer_sha256"], "upstream metadata digest mismatch")
    require(sha256(files["LICENSE"][0]) == record["license_sha256"], "upstream notice digest mismatch")
    return files


def candidate_zip(files: dict[str, tuple[bytes, int]]) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w", compression=zipfile.ZIP_STORED) as archive:
        for name, (content, mode) in sorted(files.items()):
            entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = (stat.S_IFREG | mode) << 16
            entry.compress_type = zipfile.ZIP_STORED
            archive.writestr(entry, content)
    return buffer.getvalue()


def commit_bytes(tree: str, record: dict) -> bytes:
    # Tool authorship; no human signature, external tag, or existing mirror is implied.
    identity = "CoreX Source Preparation <source-preparation@localhost> 1789344000 +0000"
    return (f"tree {tree}\nparent {record['commit']}\nauthor {identity}\n"
            f"committer {identity}\n\nPrepare fixed metadata for {record['name']} "
            f"{record['version']}\n\nUpstream: {record['commit']}\n").encode()


def build() -> dict[Path, bytes]:
    outputs = {}
    package_records = {}
    source_records = {}
    patches = []
    for label, record in PACKAGES.items():
        files = read_source(label, record)
        before = files["composer.json"][0]
        metadata = json.loads(before)
        require(metadata["name"] == record["upstream"] and metadata["license"] == "MIT", "unexpected upstream identity/license")
        expected = json.loads(before)
        expected["name"] = record["name"]
        metadata["name"] = record["name"]
        if label == "tenancy":
            upstream_provider = metadata["require"].pop("stancl/jobpipeline")
            require(upstream_provider == "2.0.0-rc7", "unexpected upstream jobpipeline constraint")
            metadata["require"][PACKAGES["jobpipeline"]["name"]] = PACKAGES["jobpipeline"]["version"]
            del expected["require"]["stancl/jobpipeline"]
            expected["require"][PACKAGES["jobpipeline"]["name"]] = PACKAGES["jobpipeline"]["version"]
        require(metadata == expected and "replace" not in metadata and "provide" not in metadata, "undeclared metadata semantics")
        # Keep all upstream formatting and fields except the approved textual link/name edits.
        after = before.replace(json.dumps(record["upstream"]).encode(), json.dumps(record["name"]).encode(), 1)
        if label == "tenancy":
            after = after.replace(b'"stancl/jobpipeline": "2.0.0-rc7"', b'"axiomasoft/stancl-jobpipeline": "2.0.0-alpha1"', 1)
        require(json.loads(after) == metadata, "metadata patch mismatch")
        modified = {**files, "composer.json": (after, files["composer.json"][1])}
        changed = [name for name in files if modified[name] != files[name]]
        require(changed == ["composer.json"], "undeclared source delta")
        archive = candidate_zip(modified)
        require(archive == candidate_zip(modified), f"{label}: non-deterministic ZIP")
        tree = git_tree(modified)
        commit = git_object("commit", commit_bytes(tree, record)).hex()
        patches.extend(difflib.unified_diff(before.decode().splitlines(keepends=True),
                                           after.decode().splitlines(keepends=True),
                                           fromfile=f"a/{label}/composer.json", tofile=f"b/{label}/composer.json"))
        outputs[ROOT / f"{label}-composer.json"] = after
        outputs[ROOT / f"{label}-candidate.zip"] = archive
        tag = "v" + record["version"]
        # Proposed publication URLs: never contacted or represented as existing channels.
        package_records[record["name"]] = {record["version"]: {
            **metadata, "version": record["version"],
            "source": {"type": "git", "url": f"https://github.com/{record['name']}.git", "reference": commit},
            "dist": {"type": "zip", "url": f"https://github.com/{record['name']}/releases/download/{tag}/{label}-candidate.zip",
                     "reference": commit, "shasum": hashlib.sha1(archive).hexdigest()},
        }}
        source_records[label] = {
            **record, "tag_proposal": tag, "mirror_status": "not-created-or-published-by-this-item",
            "maintainer_proposal": "Dmitry Vostrikov (CoreX maintainer)",
            "upstream_repository": f"https://github.com/archtechx/{label}.git",
            "file_count": len(files), "metadata_changed_files": changed,
            "candidate_composer_sha256": sha256(after), "candidate_tree_sha1": tree,
            "candidate_commit_sha1": commit, "candidate_zip_sha256": sha256(archive),
            "candidate_zip_sha1": hashlib.sha1(archive).hexdigest(),
        }
    outputs[ROOT.parent / "P4.1-stancl-metadata.patch"] = "".join(patches).encode()
    outputs[ROOT / "packages.json"] = json_bytes({"packages": package_records})
    outputs[ROOT / "source-map.json"] = json_bytes({
        "schema_version": "corex-p41-local-source-map/v1", "proof_class": "local-candidate-preparation",
        "owner_record": OWNER_RECORD,
        "owner_record_sha256": sha256((PLAN / OWNER_RECORD).read_bytes()),
        "owner_invocation": "$ task:plan-run 2026.09.12-№2-COREX-APPROVED-SPECS P4.1",
        "owner_delegation_sha256": sha256((PLAN / "brief/P4.1-owner-delegation.md").read_bytes()),
        "builder_sha256": sha256(Path(__file__).read_bytes()),
        "archive_recipe": "Full Git file tree; ZIP_STORED; sorted paths; UTC 1980-01-01; original regular-file modes; no directory entries",
        "metadata_policy": "Only package names and tenancy's exact jobpipeline link differ; no version in VCS composer.json; version from proposed tag/static record",
        "packages_metadata_sha256": sha256(outputs[ROOT / "packages.json"]),
        "packages": source_records,
    })
    return outputs


def check_boundary() -> None:
    baseline = json.loads((ROOT / "boundary-baseline.json").read_bytes())
    paths = subprocess.check_output(
        ["git", "ls-files", "-z", "--cached", "--others", "--exclude-standard"],
        cwd=REPOSITORY,
    ).decode().split("\0")
    prefix = PLAN.relative_to(REPOSITORY).as_posix() + "/"
    current = {name for name in paths if name and not name.startswith(prefix)
               and (REPOSITORY / name).is_file()}
    require(current == set(baseline), "foreign file additions/removals")
    for name, digest in baseline.items():
        require(sha256((REPOSITORY / name).read_bytes()) == digest, f"foreign delta: {name}")
    source_lock = (PLAN / "brief/P4.1-source-inputs/tenancy-composer.lock").read_bytes()
    require((REPOSITORY / "packages/tenancy/composer.lock").read_bytes() == source_lock, "live package lock drift")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="verify all candidate bytes and the untouched boundary without writes")
    args = parser.parse_args()
    outputs = build()
    if args.check:
        for path, expected in outputs.items():
            require(path.read_bytes() == expected, f"candidate drift: {path.name}")
        check_boundary()
        print("V-SOURCE-MAP GREEN · V-METADATA GREEN · V-LICENSE GREEN · V-BOUNDARY GREEN")
    else:
        for path, content in outputs.items():
            path.write_bytes(content)
        print(f"P4.1: generated {len(outputs)} deterministic local candidate carriers")


if __name__ == "__main__":
    main()
