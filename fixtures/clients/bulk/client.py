#!/usr/bin/env python3
"""External bulk client. Reads committed snapshots, then follows only server-provided URLs."""
from __future__ import annotations

import argparse
import hashlib
import json
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path


def sha256(payload: bytes) -> str:
    return hashlib.sha256(payload).hexdigest()


def graphql_artifacts(directory: Path) -> list[str]:
    return sorted(path.name for path in directory.iterdir() if path.suffix in {".graphql", ".gql"})


def load_snapshots(directory: Path) -> dict:
    artifacts = graphql_artifacts(directory)
    if artifacts:
        raise SystemExit(f"graphql artifact refused: {', '.join(artifacts)}")
    manifest = json.loads((directory / "manifest.json").read_text(encoding="utf-8"))
    digests = {}
    for name, expected in manifest["files"].items():
        actual = sha256((directory / name).read_bytes())
        if actual != expected:
            raise SystemExit(f"stale snapshot digest: {name}")
        digests[name] = actual
    operations = json.loads((directory / "operations.json").read_text(encoding="utf-8"))
    webhooks = json.loads((directory / "webhooks.json").read_text(encoding="utf-8"))
    by_id = {item["id"]: item for item in operations}
    return {"digests": digests, "operations": by_id, "states": set(webhooks["bulk"]["states"])}


def forward_path(base: str, path: str) -> str:
    if not path.startswith("/") or "://" in path:
        raise SystemExit("bulk URL is not a response-provided local path")
    return base.rstrip("/") + path


def request_json(url: str, *, method: str = "GET", body: dict | None = None, headers: dict[str, str] | None = None) -> tuple[int, dict[str, str], dict]:
    data = None if body is None else json.dumps(body, sort_keys=True, separators=(",", ":")).encode()
    request = urllib.request.Request(url, data=data, method=method)
    request.add_header("accept", "application/json")
    if body is not None:
        request.add_header("content-type", "application/json")
    for name, value in (headers or {}).items():
        request.add_header(name, value)
    try:
        with urllib.request.urlopen(request) as response:
            payload = json.loads(response.read())
            response_headers = {key.lower(): value for key, value in response.headers.items()}
            status = response.status
    except urllib.error.HTTPError as error:
        payload = json.loads(error.read())
        response_headers = {key.lower(): value for key, value in error.headers.items()}
        status = error.code
    if not isinstance(payload, dict):
        raise SystemExit("bulk response is not a JSON object")
    return status, response_headers, payload


def poll(base: str, status_url: str, states: set[str]) -> dict:
    status, headers, payload = request_json(forward_path(base, status_url))
    if payload.get("status") not in states:
        raise SystemExit(f"bulk status {payload.get('status')} is not published")
    if payload["status"] not in {"completed", "failed", "cancelled", "expired"}:
        if "retry-after" not in headers:
            raise SystemExit("active bulk poll is missing Retry-After")
        time.sleep(int(headers["retry-after"]))
    if status not in {200, 202}:
        raise SystemExit(f"bulk poll returned {status}")
    return payload


def run(base: str, contract: dict) -> dict:
    submit = contract["operations"]["bulk-operations.submit"]
    show = contract["operations"]["bulk-operations.show"]
    if submit["idempotency"] != "required" or show["method"] != "GET":
        raise SystemExit("published bulk contract drifted")
    imported = submit_operation(
        base,
        submit,
        {"kind": "import", "rows": [{"serial": "A"}, {"serial": ""}]},
        "idem-import",
    )
    status_url = imported["status_url"]
    viewed = imported
    while viewed["status"] not in {"completed", "failed", "cancelled", "expired"}:
        viewed = poll(base, status_url, contract["states"])
    if viewed["status"] != "completed" or viewed.get("has_failures") is not True:
        raise SystemExit("partial row failure did not complete with has_failures")
    result_status, _, result = request_json(forward_path(base, viewed["result_url"]))
    failure_status, _, failures = request_json(forward_path(base, viewed["failures_url"]))
    if result_status != 200 or failure_status != 200:
        raise SystemExit("result or failures were not readable")

    cancelling = submit_operation(
        base,
        submit,
        {"kind": "cancel-demo", "rows": [{"serial": "B"}, {"serial": "C"}]},
        "idem-cancel",
    )
    running = poll(base, cancelling["status_url"], contract["states"])
    if running["status"] != "running":
        raise SystemExit("cancel fixture did not leave a completed row in progress")
    cancel = contract["operations"]["bulk-operations.cancel"]
    cancel_status, _, cancelled = request_json(
        forward_path(base, running["cancel_url"]),
        method=cancel["method"],
        body={},
        headers={"Idempotency-Key": "idem-cancel-action"},
    )
    if cancel_status != cancel["success_status"] or cancelled["status"] not in {"cancelled", "cancelling"}:
        raise SystemExit("cancellation did not return a published terminal status")
    return {
        "digests": contract["digests"],
        "import": {"operation_id": imported["operation_id"], "status_url": imported["status_url"], "result": result, "failures": failures},
        "cancel": {"operation_id": cancelling["operation_id"], "status": cancelled["status"]},
    }


def submit_operation(base: str, submit: dict, body: dict, key: str) -> dict:
    status, headers, payload = request_json(
        base + submit["path"],
        method="POST",
        body=body,
        headers={"Idempotency-Key": key},
    )
    replay_status, _, replay = request_json(
        base + submit["path"],
        method="POST",
        body=body,
        headers={"Idempotency-Key": key},
    )
    if status != submit["success_status"] or replay_status != status or replay["operation_id"] != payload["operation_id"]:
        raise SystemExit("bulk submit replay diverged")
    if headers.get("location") != payload["status_url"]:
        raise SystemExit("Location and status_url diverged")
    return payload


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--snapshots", required=True)
    parser.add_argument("--base")
    parser.add_argument("--report")
    parser.add_argument("--check-only", action="store_true")
    args = parser.parse_args()
    contract = load_snapshots(Path(args.snapshots))
    if args.check_only:
        return
    if not args.base or not args.report:
        raise SystemExit("base and report are required")
    Path(args.report).write_text(json.dumps(run(args.base, contract), indent=2, sort_keys=True) + "\n", encoding="utf-8")


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:
        print(exc, file=sys.stderr)
        raise SystemExit(1) from exc
