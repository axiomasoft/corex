#!/usr/bin/env python3
"""Run external bulk and webhook clients against the committed snapshots and a local peer."""
from __future__ import annotations

import argparse
import base64
import importlib.util
import json
import shutil
import subprocess
import sys
import tempfile
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[2]
SNAPSHOTS = ROOT / "api" / "v1"
BULK_CLIENT = ROOT / "fixtures" / "clients" / "bulk" / "client.py"
WEBHOOK_CLIENT = ROOT / "fixtures" / "clients" / "webhook" / "client.py"
RECEIPT = ROOT / "release" / "1.0" / "findings" / "P17-async-client-receipt.json"
TOPIC = "equipment.maintenance"
SECRET = "whsec_" + base64.b64encode(b"synthetic-webhook-key-material!!").decode("ascii")
BODY = b'{"data":{"serial":"A"},"event_id":"evt-1","type":"equipment.maintenance"}'
DELIVERY_ID = "delivery-1"


def load_module(path: Path, name: str):
    spec = importlib.util.spec_from_file_location(name, path)
    if spec is None or spec.loader is None:
        raise RuntimeError(f"cannot load {path}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[name] = module
    spec.loader.exec_module(module)
    return module


BULK = load_module(BULK_CLIENT, "corex_bulk_client")
WEBHOOK = load_module(WEBHOOK_CLIENT, "corex_webhook_client")


def source_forbidden(path: Path) -> list[str]:
    text = path.read_text(encoding="utf-8")
    found = []
    if "CoreX\\" in text:
        found.append("CoreX\\")
    if "import graphql" in text or "from graphql" in text:
        found.append("graphql")
    return found


class BulkState:
    def __init__(self) -> None:
        self.lock = threading.Lock()
        self.operations: dict[str, dict] = {}
        self.by_key: dict[str, str] = {}
        self.effects = {"import": 0, "cancel-demo": 0}
        self.sequence = 0


class BulkHandler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    @property
    def state(self) -> BulkState:
        return self.server.state  # type: ignore[attr-defined]

    def log_message(self, format: str, *args) -> None:
        return

    def do_GET(self) -> None:
        self.route()

    def do_POST(self) -> None:
        self.route()

    def route(self) -> None:
        path = self.path.split("?", 1)[0]
        if path == "/__fixture/effects":
            self.respond(200, {"effects": self.state.effects})
            return
        if path == "/bulk-operations" and self.command == "POST":
            self.respond(202, self.submit())
            return
        operation_id, action = split_bulk_path(path)
        operation = self.state.operations.get(operation_id or "")
        if operation is None:
            self.respond(404, {"code": "resource_not_found"})
            return
        if action == "" and self.command == "GET":
            self.respond(200, self.show(operation))
            return
        if action == "cancel" and self.command == "POST":
            self.respond(202, self.cancel(operation))
            return
        if action == "result" and self.command == "GET":
            if operation["status"] != "completed":
                self.respond(409, {"code": "bulk_not_ready"})
                return
            self.respond(200, {"manifest": "sealed", "rows": operation["completed_rows"]})
            return
        if action == "failures" and self.command == "GET":
            self.respond(200, {"items": operation["failures"]})
            return
        self.respond(404, {"code": "resource_not_found"})

    def submit(self) -> dict:
        length = int(self.headers.get("Content-Length", "0"))
        body = json.loads(self.rfile.read(length).decode())
        key = self.headers.get("Idempotency-Key")
        with self.state.lock:
            if key in self.state.by_key:
                operation = self.state.operations[self.state.by_key[key]]
            else:
                self.state.sequence += 1
                operation_id = f"bulk-{self.state.sequence}"
                operation = {
                    "id": operation_id,
                    "kind": body["kind"],
                    "status": "pending",
                    "polls": 0,
                    "status_url": f"/bulk-operations/{operation_id}?track=opaque",
                    "completed_rows": [],
                    "failures": [],
                }
                self.state.operations[operation_id] = operation
                self.state.by_key[key] = operation_id
        payload = {"operation_id": operation["id"], "status": operation["status"], "status_url": operation["status_url"]}
        return payload

    def show(self, operation: dict) -> dict:
        with self.state.lock:
            operation["polls"] += 1
            if operation["kind"] == "import" and operation["polls"] == 1:
                operation["status"] = "running"
            elif operation["kind"] == "import":
                operation["status"] = "completed"
                operation["completed_rows"] = [{"serial": "A"}]
                operation["failures"] = [{"ordinal": 1, "code": "missing-serial"}]
                self.state.effects["import"] = 1
            elif operation["kind"] == "cancel-demo":
                operation["status"] = "running"
                operation["completed_rows"] = [{"serial": "B"}]
                self.state.effects["cancel-demo"] = 1
            payload = {
                "operation_id": operation["id"],
                "status": operation["status"],
                "has_failures": bool(operation["failures"]),
                "cancel_url": f"/bulk-operations/{operation['id']}/cancel",
            }
            if operation["status"] == "completed":
                payload["result_url"] = f"/bulk-operations/{operation['id']}/result?part=sealed"
                payload["failures_url"] = f"/bulk-operations/{operation['id']}/failures?page=1"
        if payload["status"] not in {"completed", "cancelled", "failed", "expired"}:
            payload["retry_after"] = "0"
        return payload

    def cancel(self, operation: dict) -> dict:
        with self.state.lock:
            if operation["status"] == "running":
                operation["status"] = "cancelled"
            return {"operation_id": operation["id"], "status": operation["status"]}

    def respond(self, status: int, payload: dict) -> None:
        body = json.dumps(payload).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        if "status_url" in payload:
            self.send_header("Location", payload["status_url"])
        if payload.get("retry_after") is not None:
            self.send_header("Retry-After", payload["retry_after"])
        self.end_headers()
        self.wfile.write(body)


def split_bulk_path(path: str) -> tuple[str | None, str]:
    prefix = "/bulk-operations/"
    if not path.startswith(prefix):
        return None, ""
    operation_id, _, action = path[len(prefix):].partition("/")
    return operation_id, action


def serve() -> tuple[ThreadingHTTPServer, str]:
    server = ThreadingHTTPServer(("127.0.0.1", 0), BulkHandler)
    server.state = BulkState()  # type: ignore[attr-defined]
    threading.Thread(target=server.serve_forever, daemon=True).start()
    host, port = server.server_address
    return server, f"http://{host}:{port}"


def run_client(script: Path, snapshots: Path, *args: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run([sys.executable, str(script), "--snapshots", str(snapshots), *args], text=True, capture_output=True, check=False)


def deliver(snapshots: Path, state: Path, report: Path, *, delivery_id: str, timestamp: int, now: int, signature: str) -> dict:
    body_path = report.with_suffix(".body")
    body_path.write_bytes(BODY)
    result = run_client(
        WEBHOOK_CLIENT,
        snapshots,
        "--state",
        str(state),
        "--report",
        str(report),
        "--delivery-id",
        delivery_id,
        "--timestamp",
        str(timestamp),
        "--now",
        str(now),
        "--topic",
        TOPIC,
        "--secret",
        SECRET,
        "--signature",
        signature,
        "--body-file",
        str(body_path),
    )
    if result.returncode != 0:
        raise SystemExit(result.stderr or result.stdout or "webhook client failed")
    return json.loads(report.read_text(encoding="utf-8"))


def overlay_snapshots(directory: Path) -> None:
    for name in ("manifest.json", "openapi.json", "operations.json", "errors.json", "webhooks.json"):
        shutil.copy(SNAPSHOTS / name, directory / name)
    webhooks = json.loads((directory / "webhooks.json").read_text(encoding="utf-8"))
    webhooks["topics"] = [TOPIC]
    encoded = json.dumps(webhooks, indent=4).encode()
    (directory / "webhooks.json").write_bytes(encoded + b"\n")
    manifest = json.loads((directory / "manifest.json").read_text(encoding="utf-8"))
    manifest["files"]["webhooks.json"] = WEBHOOK.sha256((directory / "webhooks.json").read_bytes())
    (directory / "manifest.json").write_text(json.dumps(manifest, indent=4) + "\n", encoding="utf-8")


def signed(snapshots: Path, delivery_id: str, timestamp: int) -> str:
    contract = WEBHOOK.load_snapshots(snapshots)
    return WEBHOOK.sign(SECRET, contract["webhooks"]["rotation"], delivery_id, str(timestamp), BODY)


def prove(snapshots: Path = SNAPSHOTS) -> dict:
    forbidden = {path.name: source_forbidden(path) for path in (BULK_CLIENT, WEBHOOK_CLIENT)}
    if any(forbidden.values()):
        raise SystemExit(f"client imports a forbidden surface: {forbidden}")
    server, base = serve()
    try:
        bulk = run_client(BULK_CLIENT, snapshots, "--base", base, "--report", "/tmp/p17-bulk-report.json")
        if bulk.returncode != 0:
            raise SystemExit(bulk.stderr or bulk.stdout)
        bulk_report = json.loads(Path("/tmp/p17-bulk-report.json").read_text(encoding="utf-8"))
        if bulk_report["import"]["status_url"].split("?", 1)[1] != "track=opaque":
            raise SystemExit("bulk client did not forward the opaque status URL")
        if bulk_report["import"]["failures"]["items"] != [{"code": "missing-serial", "ordinal": 1}]:
            raise SystemExit("row failure was not preserved")
        effects = json.loads(urlopen(base + "/__fixture/effects").read())["effects"]
        if effects != {"import": 1, "cancel-demo": 1}:
            raise SystemExit(f"cancellation changed completed effects: {effects}")
    finally:
        server.shutdown()

    with tempfile.TemporaryDirectory() as directory:
        target = Path(directory)
        mismatched = target / "mismatch"
        mismatched.mkdir()
        for name in ("manifest.json", "openapi.json", "operations.json", "errors.json", "webhooks.json"):
            shutil.copy(snapshots / name, mismatched / name)
        original = (mismatched / "operations.json").read_bytes()
        (mismatched / "operations.json").write_bytes(original[:-1] + (b" " if original[-1:] != b" " else b"\n"))
        mismatch = run_client(WEBHOOK_CLIENT, mismatched, "--check-only")
        if mismatch.returncode == 0 or "stale snapshot digest" not in (mismatch.stderr + mismatch.stdout):
            raise SystemExit("snapshot mismatch did not fail before action")

        committed_state = target / "committed-state.json"
        committed_report = target / "committed-report.json"
        stamp = 1_700_000_000
        undeclared = deliver(
            snapshots,
            committed_state,
            committed_report,
            delivery_id=DELIVERY_ID,
            timestamp=stamp,
            now=stamp,
            signature=signed(snapshots, DELIVERY_ID, stamp),
        )
        if undeclared["outcome"] != "undeclared-topic" or undeclared["effects"] != 0 or committed_state.exists():
            raise SystemExit("undeclared topic applied an effect")
        tampered = deliver(
            snapshots,
            committed_state,
            committed_report,
            delivery_id=DELIVERY_ID,
            timestamp=stamp,
            now=stamp,
            signature=signed(snapshots, DELIVERY_ID, stamp)[:-1] + ("A" if signed(snapshots, DELIVERY_ID, stamp)[-1] != "A" else "B"),
        )
        if tampered["outcome"] != "tamper" or tampered["effects"] != 0:
            raise SystemExit("tampered signature was accepted")

        overlay = target / "overlay"
        overlay.mkdir()
        overlay_snapshots(overlay)
        state = target / "state.json"
        report = target / "report.json"
        first = deliver(overlay, state, report, delivery_id=DELIVERY_ID, timestamp=stamp, now=stamp, signature=signed(overlay, DELIVERY_ID, stamp))
        duplicate = deliver(overlay, state, report, delivery_id=DELIVERY_ID, timestamp=stamp, now=stamp, signature=signed(overlay, DELIVERY_ID, stamp))
        replay_at = stamp + 10
        replay = deliver(overlay, state, report, delivery_id=DELIVERY_ID, timestamp=replay_at, now=replay_at, signature=signed(overlay, DELIVERY_ID, replay_at))
        bounded_at = stamp + 20
        bounded = deliver(overlay, state, report, delivery_id=DELIVERY_ID, timestamp=bounded_at, now=bounded_at, signature=signed(overlay, DELIVERY_ID, bounded_at))
        if [first["outcome"], duplicate["outcome"], replay["outcome"], bounded["outcome"]] != ["accepted", "duplicate", "duplicate", "retention"]:
            raise SystemExit("webhook duplicate or bounded replay drifted")
        if first["effects"] != 1 or bounded["effects"] != 1:
            raise SystemExit("webhook replay changed the effect count")
        retained = target / "retained-state.json"
        retained.write_text(json.dumps({"effects": 1, "deliveries": {DELIVERY_ID: {"attempts": 1, "first_seen": stamp}}}) + "\n", encoding="utf-8")
        later = stamp + 604801
        expired = deliver(overlay, retained, report, delivery_id=DELIVERY_ID, timestamp=later, now=later, signature=signed(overlay, DELIVERY_ID, later))
        if expired["outcome"] != "retention" or expired["effects"] != 1:
            raise SystemExit("replay outside retention was accepted")

    return {
        "schema_version": "corex-async-client-receipt/1",
        "item": "P17.4",
        "result": "PASS",
        "snapshots": bulk_report["digests"],
        "bulk": {"import_effect": effects["import"], "cancel_effect": effects["cancel-demo"], "cancel_status": bulk_report["cancel"]["status"]},
        "webhook": {
            "committed_topics": [],
            "undeclared_topic": undeclared["outcome"],
            "tamper": tampered["outcome"],
            "duplicate_effects": duplicate["effects"],
            "bounded_replay": bounded["outcome"],
            "retention": expired["outcome"],
            "behavior_topic": TOPIC,
        },
        "negatives": ["snapshot-mismatch", "undeclared-topic", "tamper"],
        "egress": "127.0.0.1",
    }


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
