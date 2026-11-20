#!/usr/bin/env python3
"""Loopback receiver with independently committed wire, effect, and lookup evidence."""
from __future__ import annotations

import hashlib
import json
import os
import re
import socket
import sqlite3
import threading
import time
from contextlib import closing
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit

OWNERS = frozenset({"notifications", "webhooks", "workflow"})
IDENTITY = re.compile(r"[a-zA-Z0-9._:/-]{1,191}")
MARKER = re.compile(r"provider-[a-z][a-z0-9-]*")
MAX_BODY = 65_536
WAIT_SECONDS = 180


def connect(journal: Path) -> sqlite3.Connection:
    database = sqlite3.connect(journal, timeout=5)
    database.row_factory = sqlite3.Row
    database.execute("PRAGMA synchronous = FULL")
    return database


def initialize(journal: Path) -> None:
    with closing(connect(journal)) as database:
        database.executescript("""
            PRAGMA journal_mode = WAL;
            CREATE TABLE requests (
                id INTEGER PRIMARY KEY,
                owner TEXT NOT NULL,
                effect_key TEXT NOT NULL,
                body_sha256 TEXT NOT NULL,
                mode TEXT NOT NULL,
                observed_at INTEGER NOT NULL
            );
            CREATE TABLE effects (
                id INTEGER PRIMARY KEY,
                owner TEXT NOT NULL,
                effect_key TEXT NOT NULL,
                body_sha256 TEXT NOT NULL,
                provider_ref TEXT NOT NULL,
                committed_at INTEGER NOT NULL,
                visible_at INTEGER NOT NULL
            );
            CREATE TABLE lookups (
                id INTEGER PRIMARY KEY,
                owner TEXT NOT NULL,
                effect_key TEXT NOT NULL,
                observed_at INTEGER NOT NULL,
                result TEXT NOT NULL
            );
        """)


def mark(barriers: Path, name: str, **state: object) -> None:
    path = barriers / f"{name}.reached"
    temporary = path.with_name(f"{path.name}.{threading.get_ident()}.tmp")
    try:
        temporary.write_text(json.dumps({"pid": os.getpid(), **state}), encoding="utf-8")
        os.link(temporary, path)
    finally:
        temporary.unlink(missing_ok=True)


def released(barriers: Path, name: str) -> bool:
    deadline = time.monotonic() + WAIT_SECONDS
    while not (barriers / f"{name}.release").is_file():
        if time.monotonic() >= deadline:
            return False
        time.sleep(0.025)
    return True


class ProviderServer(ThreadingHTTPServer):
    def __init__(self, barriers: Path, journal: Path):
        self.barriers = barriers
        self.journal = journal
        self.failures: list[str] = []
        super().__init__(("127.0.0.1", 0), Handler)

    def handle_error(self, request, client_address) -> None:
        self.failures.append("Provider request handling failed")
        super().handle_error(request, client_address)


class Handler(BaseHTTPRequestHandler):
    server: ProviderServer

    def log_message(self, *args: object) -> None:
        pass

    def respond(self, status: int, data: dict) -> None:
        body = json.dumps(data, separators=(",", ":")).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def request_identity(self) -> tuple[str, str, int]:
        path = urlsplit(self.path)
        prefix, _, key = path.path.rpartition("/")
        owner = prefix.removeprefix("/effects/")
        key = unquote(key)
        clock = int(self.headers.get("X-Scenario-Clock", "-1"))
        if owner not in OWNERS or not prefix.startswith("/effects/") or not IDENTITY.fullmatch(key) or clock < 0:
            raise ValueError("Invalid provider effect request")
        return owner, key, clock

    def do_POST(self) -> None:
        try:
            owner, key, clock = self.request_identity()
            mode = self.headers.get("X-Scenario-Response", "reply")
            delay = int(self.headers.get("X-Scenario-Lookup-Delay", "0"))
            idempotent = self.headers.get("X-Scenario-Idempotent", "1")
            marker = self.headers.get("X-Scenario-Barrier")
            size = int(self.headers.get("Content-Length", "-1"))
            if (mode not in {"reply", "drop", "hold"} or not 0 <= delay <= 600 or not 0 <= size <= MAX_BODY
                    or idempotent not in {"0", "1"}
                    or (marker is not None and not MARKER.fullmatch(marker)) or (mode == "hold" and marker is None)):
                raise ValueError("Invalid provider behavior")
            payload = self.rfile.read(size)
            if len(payload) != size:
                raise ValueError("Incomplete provider request")
        except ValueError:
            self.respond(400, {"status": "invalid"})
            return

        digest = hashlib.sha256(payload).hexdigest()
        with closing(connect(self.server.journal)) as database, database:
            attempt = database.execute("INSERT INTO requests(owner, effect_key, body_sha256, mode, observed_at) VALUES (?, ?, ?, ?, ?)",
                                       (owner, key, digest, mode, clock)).lastrowid
            original = database.execute("""
                SELECT body_sha256, provider_ref FROM effects WHERE owner = ? AND effect_key = ? ORDER BY id LIMIT 1
            """, (owner, key)).fetchone() if idempotent == "1" else None
            if original is None:
                reference = "scenario:" + hashlib.sha256(f"{owner}\0{key}\0{attempt}".encode()).hexdigest()
                database.execute("""
                    INSERT INTO effects(owner, effect_key, body_sha256, provider_ref, committed_at, visible_at) VALUES (?, ?, ?, ?, ?, ?)
                """, (owner, key, digest, reference, clock, clock + delay))
            else:
                reference = original["provider_ref"]
        if original is not None and original["body_sha256"] != digest:
            self.respond(409, {"status": "conflict"})
            return
        if marker is not None:
            mark(self.server.barriers, marker, owner=owner, effect_key=key, reference=reference)
        if mode == "drop":
            self.close_connection = True
            self.connection.shutdown(socket.SHUT_RDWR)
            return
        if mode == "hold" and not released(self.server.barriers, marker):
            self.server.failures.append("Provider response barrier timed out")
            self.respond(504, {"status": "timeout"})
            return
        self.respond(200, {"status": "confirmed", "reference": reference})

    def do_GET(self) -> None:
        try:
            owner, key, clock = self.request_identity()
        except ValueError:
            self.respond(400, {"status": "invalid"})
            return
        with closing(connect(self.server.journal)) as database, database:
            effect = database.execute("""
                SELECT provider_ref FROM effects WHERE owner = ? AND effect_key = ? AND visible_at <= ?
                ORDER BY id LIMIT 1
            """, (owner, key, clock)).fetchone()
            status = "confirmed" if effect else "missing"
            database.execute("INSERT INTO lookups(owner, effect_key, observed_at, result) VALUES (?, ?, ?, ?)",
                             (owner, key, clock, status))
        if effect:
            self.respond(200, {"status": status, "reference": effect["provider_ref"]})
        else:
            self.respond(404, {"status": status})


def main() -> None:
    barriers = Path(os.environ["COREX_SCENARIO_BARRIERS"])
    journal = barriers.parent / "provider-journal.sqlite"
    initialize(journal)
    with ProviderServer(barriers, journal) as server:
        worker = threading.Thread(target=server.serve_forever)
        worker.start()
        try:
            mark(barriers, "provider-ready", port=server.server_port)
            if not released(barriers, "provider-ready"):
                raise RuntimeError("Provider shutdown barrier timed out")
        finally:
            server.shutdown()
            worker.join()
        if server.failures:
            raise RuntimeError(server.failures)
    with closing(connect(journal)) as database:
        if database.execute("PRAGMA integrity_check").fetchone()[0] != "ok":
            raise RuntimeError("Provider journal failed SQLite integrity check")
        counts = {
            "requests": database.execute("SELECT count(*) FROM requests").fetchone()[0],
            "effects": database.execute("SELECT count(*) FROM effects").fetchone()[0],
            "lookups": database.execute("SELECT count(*) FROM lookups").fetchone()[0],
        }
        refs = [row["provider_ref"] for row in database.execute("SELECT provider_ref FROM effects ORDER BY owner, effect_key")]
    print(json.dumps({
        "pid": os.getpid(), "assertions": 1, "backend": f"SQLite {sqlite3.sqlite_version}",
        "connection": {"file": str(journal), "session": os.getpid()}, "refs": refs, "before": {}, "after": counts,
    }), flush=True)


if __name__ == "__main__":
    main()
