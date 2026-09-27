#!/usr/bin/env python3
"""External MCP client. Reads the published operation contract, then calls the local tools endpoint."""
from __future__ import annotations

import argparse
import hashlib
import json
import sys
import urllib.request
from pathlib import Path

RESOURCE_IDS = (
    "note:uuid:01900000-0000-7000-8000-000000000001",
    "note:uuid:550e8400-e29b-41d4-a716-446655440000",
    "note:ulid:01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "note:integer:42",
)
IDEMPOTENCY_KEYS = ("idem-uuidv7", "idem-uuid", "idem-ulid", "idem-integer")
SEMANTIC_ERROR_KEYS = ("code", "category", "path", "retryable", "details")


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
    command = next(item for item in operations if item["id"] == "notes.create")
    return {"digests": digests, "command": command}


def rpc(base: str, method: str, params: dict, request_id: int) -> dict:
    payload = json.dumps(
        {"jsonrpc": "2.0", "id": request_id, "method": method, "params": params},
        sort_keys=True,
        separators=(",", ":"),
    ).encode()
    request = urllib.request.Request(base.rstrip("/") + "/mcp", data=payload, method="POST")
    request.add_header("content-type", "application/json")
    request.add_header("accept", "application/json")
    with urllib.request.urlopen(request) as response:
        body = json.loads(response.read())
    if body.get("error"):
        raise SystemExit(f"mcp protocol error: {body['error']}")
    return body["result"]


def semantic_content(result: dict) -> dict:
    content = result.get("structuredContent")
    if not isinstance(content, dict):
        raise SystemExit("mcp result has no structuredContent")
    if result.get("isError") is True:
        return {key: content[key] for key in SEMANTIC_ERROR_KEYS}
    return content


def run(base: str, contract: dict, mode: str) -> dict:
    command = contract["command"]
    listed = rpc(base, "tools/list", {}, 1)
    tool = next((item for item in listed["tools"] if item["name"] == command["id"]), None)
    if tool is None or tool.get("contractHash") != command["contract_hash"]:
        raise SystemExit("published tool schema does not match operations.json")
    calls = []
    for resource_id, key in zip(RESOURCE_IDS, IDEMPOTENCY_KEYS, strict=True):
        if mode == "policy" and key not in {"idem-uuidv7", "idem-uuid"}:
            continue
        result = rpc(
            base,
            "tools/call",
            {
                "name": command["id"],
                "arguments": {"resource_id": resource_id, "title": "equipment"},
                "operationKey": key,
            },
            2,
        )
        calls.append({"key": key, "is_error": bool(result.get("isError")), "content": semantic_content(result)})
    return {"tool": command["id"], "contract_hash": command["contract_hash"], "calls": calls, "digests": contract["digests"]}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--snapshots", required=True)
    parser.add_argument("--base")
    parser.add_argument("--report")
    parser.add_argument("--mode", choices=("parity", "policy"), default="parity")
    parser.add_argument("--check-only", action="store_true")
    args = parser.parse_args()
    contract = load_snapshots(Path(args.snapshots))
    if args.check_only:
        return
    if not args.base or not args.report:
        raise SystemExit("base and report are required")
    Path(args.report).write_text(json.dumps(run(args.base, contract, args.mode), indent=2, sort_keys=True) + "\n", encoding="utf-8")


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:
        print(exc, file=sys.stderr)
        raise SystemExit(1) from exc
