#!/usr/bin/env python3
"""External bulk client. Reads committed snapshots, then follows only server-provided URLs."""
from __future__ import annotations

import argparse
import json
import sys
import time
import urllib.error
from urllib.parse import quote
from urllib.request import Request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from http_contract import local_url, mount, open_request, operation_url, snapshots


def load_snapshots(directory: Path) -> dict:
    manifest, documents = snapshots(directory)
    by_id = {item["id"]: item for item in documents["operations.json"]}
    return {"digests": manifest["files"], "operations": by_id,
            "mount": mount(documents["openapi.json"]),
            "states": set(documents["webhooks.json"]["bulk"]["states"])}


def forward_path(base: str, path: str) -> str:
    return local_url(base, path)


def relation_url(base: str, contract: dict, outcome: dict, relation: str) -> str:
    supplied = outcome.get(relation + "_url")
    if isinstance(supplied, str):
        return forward_path(base, supplied)
    template = contract["operations"]["bulk-operations." + relation]["path"]
    if template.count("{id}") != 1 or not isinstance(outcome.get("operation_id"), str):
        raise SystemExit("bulk response has no usable related operation reference")
    return operation_url(base, contract["mount"], template.replace("{id}", quote(outcome["operation_id"], safe="")))


def request_json(url: str, *, method: str = "GET", body: dict | None = None, headers: dict[str, str] | None = None) -> tuple[int, dict[str, str], dict]:
    data = None if body is None else json.dumps(body, sort_keys=True, separators=(",", ":")).encode()
    request = Request(url, data=data, method=method)
    request.add_header("accept", "application/json")
    if body is not None:
        request.add_header("content-type", "application/json")
    for name, value in (headers or {}).items():
        request.add_header(name, value)
    try:
        with open_request(request) as response:
            payload = json.loads(response.read())
            response_headers = {key.lower(): value for key, value in response.headers.items()}
            status = response.status
    except urllib.error.HTTPError as error:
        payload = json.loads(error.read())
        response_headers = {key.lower(): value for key, value in error.headers.items()}
        status = error.code
    if not isinstance(payload, dict):
        raise SystemExit("bulk response is not a JSON object")
    if status < 400:
        if payload.get("meta", {}).get("api_version") != "v2" or not isinstance(payload.get("data"), dict):
            raise SystemExit("bulk response is missing the v2 data envelope")
        return status, response_headers, payload["data"]
    if response_headers.get("content-type", "").split(";", 1)[0] != "application/problem+json":
        raise SystemExit("bulk error is missing a Problem media type")
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
        operation_url(base, contract["mount"], submit["path"]),
        submit,
        {"kind": "import", "source": "import", "row_limit": 1, "time_limit_seconds": 4},
        "idem-import",
    )
    status_url = imported["status_url"]
    for _ in range(20):
        viewed = poll(base, status_url, contract["states"])
        if viewed["status"] in {"completed", "failed", "cancelled", "expired"}:
            break
    else:
        raise SystemExit("bulk status did not reach a terminal state")
    if viewed["status"] != "completed" or viewed.get("has_failures") is not True:
        raise SystemExit("partial row failure did not complete with has_failures")
    result_status, _, result = request_json(relation_url(base, contract, viewed, "result"))
    failure_status, _, failures = request_json(relation_url(base, contract, viewed, "failures"))
    if result_status != 200 or failure_status != 200:
        raise SystemExit("result or failures were not readable")

    cancelling = submit_operation(
        operation_url(base, contract["mount"], submit["path"]),
        submit,
        {"kind": "import", "source": "cancel-demo", "row_limit": 1, "time_limit_seconds": 4},
        "idem-cancel",
    )
    running = poll(base, cancelling["status_url"], contract["states"])
    if running["status"] != "running":
        raise SystemExit("cancel fixture did not leave a completed row in progress")
    cancel = contract["operations"]["bulk-operations.cancel"]
    cancel_status, _, cancelled = request_json(
        relation_url(base, contract, running, "cancel"),
        method=cancel["method"],
        body={},
        headers={"Idempotency-Key": "idem-cancel-action"},
    )
    expected = 202 if cancelled["status"] == "cancelling" else cancel["success_status"]
    if cancel_status != expected or cancelled["status"] not in {"cancelled", "cancelling"}:
        raise SystemExit("cancellation did not return the declared result")
    return {
        "digests": contract["digests"],
        "import": {"operation_id": imported["operation_id"], "status_url": imported["status_url"], "result": result, "failures": failures},
        "cancel": {"operation_id": cancelling["operation_id"], "status": cancelled["status"]},
    }


def submit_operation(url: str, submit: dict, body: dict, key: str) -> dict:
    status, headers, payload = request_json(
        url,
        method="POST",
        body=body,
        headers={"Idempotency-Key": key},
    )
    replay_status, replay_headers, replay = request_json(
        url,
        method="POST",
        body=body,
        headers={"Idempotency-Key": key},
    )
    if status != submit["success_status"] or replay_status != status or replay != payload:
        raise SystemExit("bulk submit replay diverged")
    if headers.get("location") != payload["status_url"] or replay_headers.get("location") != payload["status_url"]:
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
