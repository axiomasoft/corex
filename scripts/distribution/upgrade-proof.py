#!/usr/bin/env python3
"""Qualify a fresh 2.x archive installation and the offline stage-0 import."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import sqlite3
import subprocess
import sys
import tempfile
from contextlib import closing
from pathlib import Path

from consumer_repository import assert_dist_install, load_json
from prove_scenario import profile_module, write_json
from release_candidate import CandidateError, LEGACY_SOURCE, resolve_commit
import verify_extension_fixtures as extensions

ROOT = Path(__file__).resolve().parents[2]
IMPORTER = ROOT / "scripts/migration/corex-v2/tests/rehearse.php"
WEBHOOKS = "axiomasoft/corex-webhooks"


class UpgradeError(RuntimeError):
    """The fresh target or its semantic comparison failed."""


def sqlite_rows(database: Path, query: str) -> list[tuple]:
    with closing(sqlite3.connect(f"{database.resolve().as_uri()}?mode=ro", uri=True)) as connection:
        return connection.execute(query).fetchall()


def independent_report(rehearsal: Path) -> dict:
    source = rehearsal / "source.sqlite"
    target = rehearsal / "resumed/target.sqlite"
    source_subscriptions = sqlite_rows(source, "SELECT id, status, topic, endpoint_url FROM sys_api_webhook_subscriptions ORDER BY id")
    target_subscriptions = sqlite_rows(target, """
        SELECT id, status, json_extract(subscription_json, '$.topic'),
               json_extract(subscription_json, '$.endpoint_url')
        FROM webhook_subscriptions ORDER BY id
    """)
    if len(source_subscriptions) != 2 or target_subscriptions != source_subscriptions:
        raise UpgradeError("subscription identity, status, topic or endpoint changed")

    source_rows = sqlite_rows(source, """
        SELECT d.id, d.subscription_id, d.event_id, d.status, d.body, d.body_hash, a.outcome
        FROM sys_api_webhook_deliveries d LEFT JOIN sys_api_webhook_attempts a ON a.delivery_id = d.id
        ORDER BY d.event_id
    """)
    target_rows = sqlite_rows(target, """
        SELECT d.effect_key, s.id, json_extract(d.delivery_json, '$.delivery.event_id'),
               json_extract(d.delivery_json, '$.delivery.status'), json_extract(d.delivery_json, '$.delivery.body'),
               json_extract(d.delivery_json, '$.delivery.body_hash'), json_extract(d.delivery_json, '$.attempts[0].outcome'),
               d.disposition
        FROM webhook_deliveries d JOIN webhook_subscriptions s ON d.subscription_id = s.id
        ORDER BY json_extract(d.delivery_json, '$.delivery.event_id')
    """)
    expected = [
        (hashlib.sha256(f"{subscription}|{event}".encode()).hexdigest()[:32], subscription, event, status,
         body, body_hash, outcome, "pending" if status == "pending" else "history")
        for _, subscription, event, status, body, body_hash, outcome in source_rows
    ]
    if len(expected) != 3 or target_rows != expected:
        raise UpgradeError(f"delivery intent, joined identity or recorded effect differs: {target_rows}")
    blocked = load_json(rehearsal / "unknown/plan.json.blocked.json")
    if blocked.get("status") != "blocked" or (rehearsal / "unknown/plan.json").exists():
        raise UpgradeError("unknown provider effect did not block planning")
    if sqlite_rows(rehearsal / "unknown/target.sqlite", "SELECT count(*) FROM webhook_deliveries") != [(0,)]:
        raise UpgradeError("unknown provider effect wrote target rows")

    return {
        "subscriptions": len(source_subscriptions),
        "deliveries": len(target_rows),
        "pending": sum(row[-1] == "pending" for row in target_rows),
        "history": sum(row[-1] == "history" for row in target_rows),
        "unknown_effect": "blocked",
        "source_sha256": hashlib.sha256(source.read_bytes()).hexdigest(),
        "target_sha256": hashlib.sha256(target.read_bytes()).hexdigest(),
    }


def prove(candidate: Path, baseline: Path, evidence: Path) -> dict:
    target_binding = extensions.candidate_binding(candidate)
    source_binding = extensions.candidate_binding(baseline)
    if source_binding["source_commit"] != LEGACY_SOURCE or source_binding["candidate_version"] != "1.0.0":
        raise UpgradeError("migration rehearsal requires the frozen 1.0.0 candidate")
    if not target_binding["candidate_version"].startswith("2.") or target_binding["source_commit"] != resolve_commit(ROOT, "HEAD"):
        raise UpgradeError("migration rehearsal requires the 2.x candidate checkout")
    evidence.mkdir(parents=True, exist_ok=False)
    profiles = profile_module()
    interpreter = profiles.interpreter_for("8.4", profiles.interpreters())
    with tempfile.TemporaryDirectory(prefix="corex-fresh-migration-target-") as temporary:
        root = Path(temporary)
        repository = root / "repository"
        extensions.materialize_repository(extensions.candidate_directory(candidate), repository)
        profile = {"id": "migration-prototype-boot", "database": "sqlite-single", "http": True}
        cell = {"id": profile["id"], "packages": [WEBHOOKS], "php": "8.4", "laravel": 13}
        consumer, runtime, declared = profiles.install_app(root / "app", cell, profile, repository, ROOT, interpreter, target_binding["candidate_version"])
        boot = profiles.boot_app(consumer, runtime, cell["id"])
        profiles.assert_providers(consumer, boot, cell["id"])
        lock = assert_dist_install(consumer, repository, declared, ROOT)
        app_data = {
            "profile": profile["id"], **lock, "config_cache": True,
            "providers": sorted(provider for provider in boot["providers"] if provider.startswith("CoreX\\")),
        }
        write_json(evidence / "fresh-app.json", app_data)
    rehearsal = evidence / "import"
    completed = subprocess.run(
        [interpreter["binary"], str(IMPORTER), str(rehearsal), target_binding["source_commit"]],
        cwd=ROOT, env=os.environ.copy(), text=True, capture_output=True, check=False, timeout=120,
    )
    (evidence / "import.log").write_text(completed.stdout + completed.stderr, encoding="utf-8")
    if completed.returncode:
        raise UpgradeError(f"offline importer failed with exit status {completed.returncode}; see {evidence / 'import.log'}")
    importer = json.loads(completed.stdout)
    if (importer["status"] != "verified" or importer["source_artifact"] != source_binding["source_commit"]
            or importer["target_artifact"] != target_binding["source_commit"] or importer.get("assertions", 0) <= 0):
        raise UpgradeError("offline import receipt does not match the source and target")
    semantic = independent_report(rehearsal)
    write_json(evidence / "semantic-report.json", semantic)
    receipt = {
        "schema_version": "corex-offline-migration-proof/1", "result": "PASS",
        "baseline": source_binding, "candidate": target_binding, "fresh_app": app_data,
        "importer": importer, "independent_semantics": semantic,
    }
    write_json(evidence / "receipt.json", receipt)
    return receipt


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--baseline", type=Path, required=True)
    parser.add_argument("--evidence-dir", type=Path, required=True)
    args = parser.parse_args()
    try:
        receipt = prove(args.candidate, args.baseline, args.evidence_dir.resolve())
    except (CandidateError, OSError, KeyError, ValueError, RuntimeError, sqlite3.Error, subprocess.SubprocessError) as error:
        print(f"offline migration proof denied: {error}", file=sys.stderr)
        return 1
    print(json.dumps({"result": receipt["result"], "receipt": str(args.evidence_dir / "receipt.json")}))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
