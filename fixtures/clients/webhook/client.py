#!/usr/bin/env python3
"""External webhook receiver. Verifies exact signed bytes and deduplicates on delivery identity."""
from __future__ import annotations

import argparse
import base64
import hashlib
import hmac
import json
import sys
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
    webhooks = json.loads((directory / "webhooks.json").read_text(encoding="utf-8"))
    return {"digests": digests, "webhooks": webhooks, "topics": topic_names(webhooks)}


def topic_names(webhooks: dict) -> list[str]:
    names = []
    for topic in webhooks["topics"]:
        names.append(topic if isinstance(topic, str) else str(topic["name"]))
    return names


def signing_key(secret: str, rotation: dict) -> bytes:
    prefix = rotation["prefix"]
    if not secret.startswith(prefix):
        raise SystemExit("webhook secret is missing the published prefix")
    try:
        key = base64.b64decode(secret[len(prefix):], validate=True)
    except Exception as exc:
        raise SystemExit("webhook secret is not strict base64") from exc
    if len(key) != rotation["secret_bytes"]:
        raise SystemExit("webhook secret length drifted")
    return key


def sign(secret: str, rotation: dict, delivery_id: str, timestamp: str, raw_body: bytes) -> str:
    key = signing_key(secret, rotation)
    content = delivery_id + "." + timestamp + "." + raw_body.decode("utf-8")
    digest = base64.b64encode(hmac.new(key, content.encode("utf-8"), hashlib.sha256).digest()).decode("ascii")
    return rotation["signature"] + "," + digest


def signature_matches(secret: str, rotation: dict, delivery_id: str, timestamp: str, raw_body: bytes, presented: str) -> bool:
    expected = sign(secret, rotation, delivery_id, timestamp, raw_body)
    return hmac.compare_digest(expected, presented)


def apply_delivery(
    contract: dict,
    *,
    delivery_id: str,
    timestamp: int,
    now: int,
    topic: str,
    raw_body: bytes,
    signature: str,
    secret: str,
    state: dict,
) -> dict:
    rotation = contract["webhooks"]["rotation"]
    replay = contract["webhooks"]["replay"]
    if abs(now - timestamp) > rotation["timestamp_window_seconds"]:
        return finish(state, "expired-timestamp", False)
    if not signature_matches(secret, rotation, delivery_id, str(timestamp), raw_body, signature):
        return finish(state, "tamper", False)
    if topic not in contract["topics"]:
        return finish(state, "undeclared-topic", False)
    deliveries = state.setdefault("deliveries", {})
    current = deliveries.get(delivery_id)
    if current is None:
        if now - timestamp > replay["body_retention_seconds"]:
            return finish(state, "retention", False)
        deliveries[delivery_id] = {"attempts": 1, "first_seen": now}
        state["effects"] = state.get("effects", 0) + 1
        return finish(state, "accepted", True)
    if now - current["first_seen"] > replay["horizon_seconds"] or current["attempts"] >= replay["max_count"]:
        return finish(state, "retention", False)
    current["attempts"] += 1
    return finish(state, "duplicate", True)


def finish(state: dict, outcome: str, accepted: bool) -> dict:
    return {"outcome": outcome, "accepted": accepted, "effects": state.get("effects", 0), "state": state}


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--snapshots", required=True)
    parser.add_argument("--check-only", action="store_true")
    parser.add_argument("--state")
    parser.add_argument("--report")
    parser.add_argument("--delivery-id")
    parser.add_argument("--timestamp", type=int)
    parser.add_argument("--now", type=int)
    parser.add_argument("--topic")
    parser.add_argument("--secret")
    parser.add_argument("--signature")
    parser.add_argument("--body-file")
    args = parser.parse_args()
    contract = load_snapshots(Path(args.snapshots))
    if args.check_only:
        return
    required = (args.state, args.report, args.delivery_id, args.timestamp, args.now, args.topic, args.secret, args.signature, args.body_file)
    if any(value is None for value in required):
        raise SystemExit("delivery arguments are required")
    state_path = Path(args.state)
    state = json.loads(state_path.read_text(encoding="utf-8")) if state_path.exists() else {"effects": 0, "deliveries": {}}
    raw = Path(args.body_file).read_bytes()
    result = apply_delivery(
        contract,
        delivery_id=args.delivery_id,
        timestamp=args.timestamp,
        now=args.now,
        topic=args.topic,
        raw_body=raw,
        signature=args.signature,
        secret=args.secret,
        state=state,
    )
    if result["accepted"]:
        state_path.write_text(json.dumps(result["state"], indent=2, sort_keys=True) + "\n", encoding="utf-8")
    Path(args.report).write_text(json.dumps({key: value for key, value in result.items() if key != "state"}, indent=2, sort_keys=True) + "\n", encoding="utf-8")


if __name__ == "__main__":
    try:
        main()
    except SystemExit:
        raise
    except Exception as exc:
        print(exc, file=sys.stderr)
        raise SystemExit(1) from exc
