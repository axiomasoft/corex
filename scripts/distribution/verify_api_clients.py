#!/usr/bin/env python3
"""Run external REST and MCP clients against a local protocol server and the committed snapshots."""
from __future__ import annotations

import argparse
import hashlib
import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[2]
SNAPSHOTS = ROOT / "api" / "v2"
REST_CLIENT = ROOT / "fixtures" / "clients" / "rest" / "client.py"
MCP_CLIENT = ROOT / "fixtures" / "clients" / "mcp" / "client.py"
RECEIPT = ROOT / "build" / "distribution" / "api-client-receipt.json"
API = "/api/v2"
CURSOR_TOKEN = "v2.opaque%2Etoken"
CURSOR = f"{API}/webhook-deliveries?cursor={CURSOR_TOKEN}"
TOOL_NAME = "notes_create__v2"


def load_module(path: Path, name: str):
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"cannot load {path}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[name] = module
    spec.loader.exec_module(module)
    return module


REST = load_module(REST_CLIENT, "corex_rest_client")
MCP = load_module(MCP_CLIENT, "corex_mcp_client")


def source_forbidden(path: Path) -> list[str]:
    text = path.read_text(encoding="utf-8")
    found = []
    if "CoreX\\" in text:
        found.append("CoreX\\")
    if "import graphql" in text or "from graphql" in text:
        found.append("graphql")
    return found


class ProtocolState:
    def __init__(self, snapshots: Path, *, unexpected: bool) -> None:
        contract = REST.load_snapshots(snapshots)
        self.command = REST.operation(contract, "notes.create")
        self.query = REST.operation(contract, "webhook-deliveries.list")
        if contract["manifest"]["major"] != "v2" or any(row["version"] != "v2" for row in (self.command, self.query)):
            raise SystemExit("published REST operations must use literal v2")
        self.errors = {item["code"]: item for item in contract["errors"] if item["code"] in {"operations.resource_not_found", "operations.access_denied"}}
        self.unexpected = unexpected
        self.lock = threading.Lock()
        self.records: dict[str, dict] = {}
        self.effects: dict[str, int] = {}
        self.policy: dict[str, str] = {}


def descriptor_status(category: str) -> int:
    return {"authorization": 403, "not_found": 404, "validation": 422}.get(category, 400)


class ProtocolHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    @property
    def state(self) -> ProtocolState:
        return self.server.state  # type: ignore[attr-defined]

    def log_message(self, format: str, *args) -> None:
        return

    def do_GET(self) -> None:
        self.route()

    def do_POST(self) -> None:
        self.route()

    def route(self) -> None:
        path, _, query = self.path.partition("?")
        if path == "/__fixture/effects" and self.command == "GET":
            self.respond(200, {"effects": self.state.effects}, error=False)
            return
        if path == "/__fixture/revoke" and self.command == "POST":
            body = self.read_json()
            with self.state.lock:
                self.state.policy[body["key"]] = body["mode"]
                self.respond(200, {"data": {"mode": body["mode"]}, "meta": self.meta()}, error=False)
            return
        if path == API + self.state.query["path"] and self.command == "GET":
            status, payload, error = self.state.query["success_status"], self.page(query), False
            self.respond(status, payload, error=error)
            return
        if path == API + self.state.command["path"] and self.command == "POST":
            status, payload, error = self.command_result(self.header("Idempotency-Key"), self.read_json())
            self.respond(status, payload, error=error)
            return
        if path == API + "/notes/missing" and self.command == "GET":
            status, payload, error = self.error_result("operations.resource_not_found")
            self.respond(status, payload, error=error)
            return
        if path == "/mcp" and self.command == "POST":
            message = self.read_json()
            if message.get("method") == "notifications/initialized" and "id" not in message:
                self.send_response(202)
                self.send_header("Content-Length", "0")
                self.end_headers()
            else:
                self.respond(200, self.mcp(message), error=False, content_type="application/json")
            return
        status, payload, error = self.error_result("operations.resource_not_found")
        self.respond(status, payload, error=error)

    def page(self, query: str) -> dict:
        token = raw_query_value(query, "cursor")
        if token is None:
            data = {"items": [{"id": "delivery-1"}], "next": CURSOR}
        elif token == CURSOR_TOKEN:
            data = {"items": [{"id": "delivery-2"}], "next": None}
        else:
            raise RuntimeError("cursor token was not forwarded verbatim")
        return {"data": data, "meta": self.meta()}

    def command_result(self, key: str | None, body: dict) -> tuple[int, dict, bool]:
        if not key:
            return self.error_result("operations.access_denied")
        with self.state.lock:
            policy = self.state.policy.get(key)
            if policy == "record":
                return self.error_result("operations.access_denied")
            stored = self.state.records.get(key)
            replayed = stored is not None
            if stored is None:
                stored = {"id": f"note-{len(self.state.records) + 1}", "resource_id": body["resource_id"], "title": body["title"], "private_label": "sealed"}
                self.state.records[key] = stored
                self.state.effects[key] = self.state.effects.get(key, 0) + 1
            data = dict(stored)
            if policy == "field":
                data.pop("private_label", None)
        return self.state.command["success_status"], {"data": data, "meta": {**self.meta(), "replayed": replayed}}, False

    def mcp(self, message: dict) -> dict:
        method = message.get("method")
        if method == "initialize":
            result = {"protocolVersion": "2025-11-25", "capabilities": {"tools": {}}, "serverInfo": {"name": "fixture", "version": "2.0.0"}}
        elif method == "tools/list":
            operation = self.state.command
            result = {
                "tools": [
                    {
                        "name": TOOL_NAME,
                        "canonicalCode": operation["id"],
                        "toolVersion": "v2",
                        "inputSchema": operation["request_schema"],
                        "outputSchema": operation["response_schema"],
                        "operationRef": "v2:" + operation["id"],
                        "operationContractHash": operation["contract_hash"],
                        "contractHash": hashlib.sha256(TOOL_NAME.encode()).hexdigest(),
                    }
                ]
            }
        elif method == "tools/call":
            params = message["params"]
            if params.get("name") != TOOL_NAME:
                return {"jsonrpc": "2.0", "id": message.get("id"), "error": {"code": -32602, "message": "tool unavailable"}}
            _, payload, error = self.command_result(params.get("operationKey"), params["arguments"])
            structured = payload if error else payload["data"]
            result = {"isError": error, "structuredContent": structured,
                      "content": [{"type": "text", "text": json.dumps(structured)}],
                      "outcome": "not_executed" if error else "completed",
                      "replayed": False if error else payload["meta"]["replayed"]}
        else:
            return {"jsonrpc": "2.0", "id": message.get("id"), "error": {"code": -32601, "message": "method not found"}}
        return {"jsonrpc": "2.0", "id": message.get("id"), "result": result}

    def error_result(self, code: str) -> tuple[int, dict, bool]:
        descriptor = self.state.errors[code]
        status = descriptor_status(descriptor["category"])
        payload = {
            "type": "urn:corex:problem:" + code,
            "title": code,
            "status": status,
            "detail": code,
            "code": descriptor["code"],
            "category": descriptor["category"],
            "path": descriptor["path"],
            "retryable": descriptor["retryable"],
            "details": {},
            **self.meta(),
        }
        return status, payload, True

    def meta(self) -> dict:
        return {"request_id": "req-fixture", "correlation_id": "corr-fixture", "api_version": "v2"}

    def header(self, name: str) -> str | None:
        value = self.headers.get(name)
        return value if isinstance(value, str) else None

    def read_json(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        payload = json.loads(self.rfile.read(length).decode())
        if not isinstance(payload, dict):
            raise ValueError("JSON object required")
        return payload

    def respond(self, status: int, payload: dict, *, error: bool, content_type: str | None = None) -> None:
        if self.state.unexpected and "jsonrpc" not in payload:
            payload = {**payload, "unexpected": True}
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", content_type or ("application/problem+json" if error else "application/json"))
        self.send_header("Content-Length", str(len(body)))
        self.send_header("X-Request-ID", "req-fixture")
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def raw_query_value(query: str, name: str) -> str | None:
    for part in query.split("&"):
        if part.startswith(name + "="):
            return part[len(name) + 1 :]
    return None


def serve(snapshots: Path, *, unexpected: bool = False) -> tuple[ThreadingHTTPServer, str]:
    server = ThreadingHTTPServer(("127.0.0.1", 0), ProtocolHandler)
    server.state = ProtocolState(snapshots, unexpected=unexpected)  # type: ignore[attr-defined]
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    host, port = server.server_address
    return server, f"http://{host}:{port}"


def run_client(script: Path, snapshots: Path, *args: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run([sys.executable, str(script), "--snapshots", str(snapshots), *args], text=True, capture_output=True, check=False)


def fixture_json(url: str, *, method: str = "GET", body: dict | None = None) -> dict:
    data = None if body is None else json.dumps(body).encode()
    request = Request(url, data=data, method=method)
    if body is not None:
        request.add_header("content-type", "application/json")
    try:
        with urlopen(request) as response:
            return json.loads(response.read())
    except HTTPError as error:
        raise RuntimeError(error.read().decode()) from error


def prove(snapshots: Path = SNAPSHOTS) -> dict:
    with tempfile.TemporaryDirectory(prefix="corex-protocol-api-") as temporary:
        return prove_clients(snapshots, Path(temporary))


def prove_clients(snapshots: Path, reports: Path) -> dict:
    forbidden = {path.name: source_forbidden(path) for path in (REST_CLIENT, MCP_CLIENT)}
    if any(forbidden.values()):
        raise SystemExit(f"client imports a forbidden surface: {forbidden}")
    server, base = serve(snapshots)
    try:
        rest_path = reports / "rest.json"
        rest = run_client(REST_CLIENT, snapshots, "--base", base, "--report", str(rest_path))
        if rest.returncode != 0:
            raise SystemExit(rest.stderr or rest.stdout)
        rest_report = json.loads(rest_path.read_text(encoding="utf-8"))
        if rest_report["query"]["cursor"] != CURSOR:
            raise SystemExit("cursor was not forwarded as an opaque response path")
        if rest_report["query"]["pages"] != [[{"id": "delivery-1"}], [{"id": "delivery-2"}]]:
            raise SystemExit("cursor continuation did not preserve page order")
        parity_path = reports / "mcp-parity.json"
        mcp = run_client(MCP_CLIENT, snapshots, "--base", base, "--mode", "parity", "--report", str(parity_path))
        if mcp.returncode != 0:
            raise SystemExit(mcp.stderr or mcp.stdout)
        parity = json.loads(parity_path.read_text(encoding="utf-8"))
        rest_by_key = {item["key"]: item["data"] for item in rest_report["commands"]}
        for call in parity["calls"]:
            if call["is_error"] or call["content"] != rest_by_key[call["key"]]:
                raise SystemExit(f"REST/MCP parity failed for {call['key']}")
        fixture_json(base + "/__fixture/revoke", method="POST", body={"key": "idem-uuidv7", "mode": "record"})
        fixture_json(base + "/__fixture/revoke", method="POST", body={"key": "idem-uuid", "mode": "field"})
        policy_path = reports / "mcp-policy.json"
        policy = run_client(MCP_CLIENT, snapshots, "--base", base, "--mode", "policy", "--report", str(policy_path))
        if policy.returncode != 0:
            raise SystemExit(policy.stderr or policy.stdout)
        policy_report = json.loads(policy_path.read_text(encoding="utf-8"))
        by_key = {item["key"]: item for item in policy_report["calls"]}
        if not by_key["idem-uuidv7"]["is_error"] or by_key["idem-uuidv7"]["content"]["code"] != "operations.access_denied":
            raise SystemExit("record revocation did not deny the MCP replay")
        if by_key["idem-uuid"]["is_error"] or "private_label" in by_key["idem-uuid"]["content"]:
            raise SystemExit("field revocation did not prune the MCP replay")
        effects = fixture_json(base + "/__fixture/effects")["effects"]
        if any(count != 1 for count in effects.values()) or set(effects) != set(REST.IDEMPOTENCY_KEYS):
            raise SystemExit(f"replay changed the effect count: {effects}")
    finally:
        server.shutdown()
        server.server_close()

    poisoned, poisoned_base = serve(snapshots, unexpected=True)
    try:
        unexpected = run_client(REST_CLIENT, snapshots, "--base", poisoned_base, "--report", str(reports / "unexpected.json"))
        if unexpected.returncode == 0 or "unexpected field" not in (unexpected.stderr + unexpected.stdout):
            raise SystemExit("unexpected field was accepted")
    finally:
        poisoned.shutdown()
        poisoned.server_close()

    with_temp(snapshots, "stale")
    with_temp(snapshots, "graphql")
    return {
        "schema_version": "corex-api-client-receipt/1",
        "item": json.loads(Path(__file__).with_name("legacy-receipt-items.json").read_text())["corex-api-client-receipt/1"],
        "result": "PASS",
        "snapshots": rest_report["digests"],
        "contract_fingerprint": json.loads((snapshots / "manifest.json").read_text(encoding="utf-8"))["contract_fingerprint"],
        "query": rest_report["query"]["pages"],
        "resource_ids": [item["data"]["resource_id"] for item in rest_report["commands"]],
        "error": rest_report["errors"],
        "mcp_tool": parity["tool"],
        "mcp_contract_hash": parity["contract_hash"],
        "effects": effects,
        "negatives": ["stale-digest", "unexpected-field", "graphql-artifact"],
        "revocation": {"record": "operations.access_denied", "field": "private-label-pruned"},
    }


def with_temp(snapshots: Path, kind: str) -> None:
    with tempfile.TemporaryDirectory() as directory:
        target = Path(directory)
        for name in ("manifest.json", "openapi.json", "operations.json", "errors.json", "webhooks.json"):
            shutil.copy(snapshots / name, target / name)
        if kind == "stale":
            payload = (target / "operations.json").read_bytes()
            (target / "operations.json").write_bytes(payload[:-1] + (b" " if payload[-1:] != b" " else b"\n"))
        else:
            (target / "schema.graphql").write_text("type Query { note: String }\n", encoding="utf-8")
        result = run_client(REST_CLIENT, target, "--check-only")
        expected = "stale snapshot digest" if kind == "stale" else "graphql artifact refused"
        if result.returncode == 0 or expected not in (result.stderr + result.stdout):
            raise SystemExit(f"{kind} negative did not fail closed")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--receipt", type=Path, default=RECEIPT)
    args = parser.parse_args()
    receipt = prove()
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.receipt.write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    print(json.dumps({"result": receipt["result"], "receipt": str(args.receipt)}))


if __name__ == "__main__":
    main()
