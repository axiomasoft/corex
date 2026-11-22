#!/usr/bin/env python3
"""External REST client. Reads committed api/v2 snapshots, then calls one local server."""
from __future__ import annotations

import argparse
import json
import sys
import urllib.error
from urllib.request import Request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from http_contract import local_url, mount, open_request, operation_url, snapshots

RESOURCE_IDS = (
    "note:uuid:01900000-0000-7000-8000-000000000001",
    "note:uuid:550e8400-e29b-41d4-a716-446655440000",
    "note:ulid:01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "note:integer:42",
)
IDEMPOTENCY_KEYS = ("idem-uuidv7", "idem-uuid", "idem-ulid", "idem-integer")
ENVELOPE_KEYS = frozenset({"data", "error", "meta"})
PROBLEM_KEYS = frozenset({
    "type", "title", "status", "detail", "instance",
    "code", "category", "path", "retryable", "details",
    "request_id", "correlation_id", "api_version",
})
SEMANTIC_ERROR_KEYS = ("code", "category", "path", "retryable", "details")


def load_snapshots(directory: Path) -> dict:
    manifest, documents = snapshots(directory)
    return {"manifest": manifest, "digests": manifest["files"],
            "operations": documents["operations.json"], "errors": documents["errors.json"],
            "openapi": documents["openapi.json"], "mount": mount(documents["openapi.json"])}


def operation(contract: dict, operation_id: str) -> dict:
    for item in contract["operations"]:
        if item["id"] == operation_id:
            return item
    raise SystemExit(f"operation {operation_id} is not in the published snapshot")


def error_descriptor(contract: dict, code: str) -> dict:
    matches = [item for item in contract["errors"] if item["code"] == code]
    if len(matches) != 1:
        raise SystemExit(f"error {code} is not a single published descriptor")
    return matches[0]


def unexpected_fields(payload: dict, media: str = "application/json") -> list[str]:
    allowed = PROBLEM_KEYS if media == "application/problem+json" else ENVELOPE_KEYS
    return sorted(set(payload) - allowed)


def semantic_error(payload: dict, media: str) -> dict:
    source = payload["error"] if media == "application/json" else payload
    return {key: source[key] for key in SEMANTIC_ERROR_KEYS}


def forward_cursor(base: str, nxt: str) -> str:
    return local_url(base, nxt)


def request_json(url: str, *, method: str = "GET", body: dict | None = None, headers: dict[str, str] | None = None) -> tuple[int, dict[str, str], dict]:
    data = None if body is None else json.dumps(body, sort_keys=True, separators=(",", ":")).encode()
    request = Request(url, data=data, method=method)
    supplied = {name.lower(): value for name, value in (headers or {}).items()}
    accept = supplied.get("accept", "application/json")
    request.add_header("accept", accept)
    if body is not None:
        request.add_header("content-type", "application/json")
    for name, value in supplied.items():
        if name != "accept":
            request.add_header(name, value)
    try:
        with open_request(request) as response:
            raw = response.read()
            response_headers = {key.lower(): value for key, value in response.headers.items()}
            status = response.status
    except urllib.error.HTTPError as error:
        raw = error.read()
        response_headers = {key.lower(): value for key, value in error.headers.items()}
        status = error.code
    payload = json.loads(raw)
    if not isinstance(payload, dict):
        raise SystemExit("response is not a JSON object")
    media = response_headers.get("content-type", "").split(";", 1)[0]
    expected_media = "application/problem+json" if status >= 400 else "application/json"
    if media != expected_media:
        raise SystemExit(f"unexpected response media type: {media}")
    if status < 400 and (payload.get("meta", {}).get("api_version") != "v2" or not isinstance(payload.get("data"), dict)):
        raise SystemExit("success response is missing the v2 data envelope")
    if status >= 400 and payload.get("api_version") != "v2":
        raise SystemExit("Problem response is missing its API major")
    extra = unexpected_fields(payload, media)
    if extra:
        raise SystemExit(f"unexpected field: {', '.join(extra)}")
    return status, response_headers, payload


def assert_error(contract: dict, status: int, headers: dict[str, str], payload: dict, media: str) -> dict:
    if "no-store" not in {item.strip() for item in headers.get("cache-control", "").split(",")}:
        raise SystemExit("error response is missing published cache headers")
    semantic = semantic_error(payload, media)
    descriptor = error_descriptor(contract, semantic["code"])
    if semantic["category"] != descriptor["category"] or semantic["retryable"] is not descriptor["retryable"]:
        raise SystemExit(f"error {semantic['code']} drifted from errors.json")
    if semantic["path"] != descriptor["path"]:
        raise SystemExit(f"error {semantic['code']} path drifted")
    if status != (403 if semantic["category"] == "authorization" else 404 if semantic["category"] == "not_found" else status):
        raise SystemExit("error status does not match the descriptor category")
    return semantic


def run(base: str, contract: dict) -> dict:
    query = operation(contract, "webhook-deliveries.list")
    command = operation(contract, "notes.create")
    if query["method"] != "GET" or command["method"] != "POST":
        raise SystemExit("published methods do not match the client calls")
    if query["path"] not in contract["openapi"]["paths"] or command["path"] not in contract["openapi"]["paths"]:
        raise SystemExit("openapi is missing the published paths")

    status, _, first = request_json(operation_url(base, contract["mount"], query["path"]))
    if status != query["success_status"]:
        raise SystemExit("query status drifted")
    nxt = first["data"]["next"]
    continued = forward_cursor(base, nxt)
    status, _, second = request_json(continued)
    if status != query["success_status"] or second["data"]["next"] is not None:
        raise SystemExit("cursor continuation did not finish the published page")

    created = []
    for resource_id, key in zip(RESOURCE_IDS, IDEMPOTENCY_KEYS, strict=True):
        body = {"resource_id": resource_id, "title": "equipment"}
        status, _, payload = request_json(
            operation_url(base, contract["mount"], command["path"]),
            method="POST",
            body=body,
            headers={"Idempotency-Key": key},
        )
        if status != command["success_status"] or payload["data"]["resource_id"] != resource_id:
            raise SystemExit(f"resource id did not round-trip: {resource_id}")
        replay_status, _, replay = request_json(
            operation_url(base, contract["mount"], command["path"]),
            method="POST",
            body=body,
            headers={"Idempotency-Key": key},
        )
        if replay_status != status or replay["data"] != payload["data"]:
            raise SystemExit(f"idempotent replay diverged for {key}")
        created.append({"key": key, "data": payload["data"]})

    missing = operation_url(base, contract["mount"], "/notes/missing")
    json_status, json_headers, json_body = request_json(missing, headers={"Accept": "application/json"})
    problem_status, problem_headers, problem_body = request_json(missing, headers={"Accept": "application/problem+json"})
    json_error = assert_error(contract, json_status, json_headers, json_body, "application/problem+json")
    problem_error = assert_error(contract, problem_status, problem_headers, problem_body, "application/problem+json")
    if json_status != problem_status or json_error != problem_error:
        raise SystemExit("error media types diverged")

    return {
        "query": {"pages": [first["data"]["items"], second["data"]["items"]], "cursor": nxt},
        "commands": created,
        "errors": {"status": json_status, "semantic": json_error},
        "digests": contract["digests"],
    }


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
    report = run(args.base, contract)
    Path(args.report).write_text(json.dumps(report, indent=2, sort_keys=True) + "\n", encoding="utf-8")


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:
        print(exc, file=sys.stderr)
        raise SystemExit(1) from exc
