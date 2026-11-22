#!/usr/bin/env python3
"""External MCP client. Reads the published operation contract, then calls the local tools endpoint."""
from __future__ import annotations

import argparse
import json
import sys
from urllib.request import Request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from http_contract import open_request, snapshots

RESOURCE_IDS = (
    "note:uuid:01900000-0000-7000-8000-000000000001",
    "note:uuid:550e8400-e29b-41d4-a716-446655440000",
    "note:ulid:01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "note:integer:42",
)
IDEMPOTENCY_KEYS = ("idem-uuidv7", "idem-uuid", "idem-ulid", "idem-integer")
SEMANTIC_ERROR_KEYS = ("code", "category", "path", "retryable", "details")


def load_snapshots(directory: Path) -> dict:
    manifest, documents = snapshots(directory)
    command = next(item for item in documents["operations.json"] if item["id"] == "notes.create")
    if command["version"] != "v2":
        raise SystemExit("tool target must use operation major v2")
    return {"digests": manifest["files"], "command": command}


def rpc(base: str, method: str, params: dict, request_id: int | None) -> dict:
    payload = json.dumps(
        {"jsonrpc": "2.0", "method": method, "params": params, **({"id": request_id} if request_id is not None else {})},
        sort_keys=True,
        separators=(",", ":"),
    ).encode()
    request = Request(base.rstrip("/") + "/mcp", data=payload, method="POST")
    request.add_header("content-type", "application/json")
    request.add_header("accept", "application/json")
    with open_request(request) as response:
        raw = response.read()
        if request_id is None:
            if response.status != 202 or raw:
                raise SystemExit("initialization notification must be accepted without a body")
            return {}
        body = json.loads(raw)
    if body.get("jsonrpc") != "2.0" or body.get("id") != request_id:
        raise SystemExit("MCP response does not match the JSON-RPC request")
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
    initialized = rpc(base, "initialize", {"protocolVersion": "2025-11-25", "capabilities": {}}, 1)
    if initialized.get("protocolVersion") != "2025-11-25":
        raise SystemExit("MCP protocol version drifted")
    rpc(base, "notifications/initialized", {}, None)
    listed = rpc(base, "tools/list", {}, 2)
    tool = next((item for item in listed["tools"] if item.get("operationRef") == "v2:" + command["id"]), None)
    if (tool is None or tool.get("toolVersion") != "v2"
            or tool.get("operationContractHash") != command["contract_hash"]
            or tool.get("contractHash") in {None, command["contract_hash"]}
            or tool.get("inputSchema") != command["request_schema"]
            or tool.get("outputSchema") != command["response_schema"]):
        raise SystemExit("published tool does not match the compiled v2 operation contract")
    calls = []
    for resource_id, key in zip(RESOURCE_IDS, IDEMPOTENCY_KEYS, strict=True):
        if mode == "policy" and key not in {"idem-uuidv7", "idem-uuid"}:
            continue
        result = rpc(
            base,
            "tools/call",
            {
                "name": tool["name"],
                "arguments": {"resource_id": resource_id, "title": "equipment"},
                "operationKey": key,
            },
            3 + len(calls),
        )
        calls.append({"key": key, "is_error": bool(result.get("isError")), "content": semantic_content(result)})
    return {"tool": tool["name"], "contract_hash": command["contract_hash"], "calls": calls, "digests": contract["digests"]}


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
