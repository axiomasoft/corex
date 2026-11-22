"""Verify the five v2 carriers and keep navigation within the chosen origin."""

from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
from urllib.parse import urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener

CARRIERS = frozenset({"errors.json", "openapi.json", "operations.json", "webhooks.json"})


def snapshots(directory: Path) -> tuple[dict, dict]:
    entries = {path.name for path in directory.iterdir()}
    graphql = sorted(name for name in entries if Path(name).suffix in {".graphql", ".gql"})
    if graphql:
        raise SystemExit(f"graphql artifact refused: {', '.join(graphql)}")
    if entries != CARRIERS | {"manifest.json"}:
        raise SystemExit("expected exactly the five published v2 carriers")
    manifest = json.loads((directory / "manifest.json").read_bytes())
    if manifest.get("major") != "v2" or set(manifest.get("files", {})) != CARRIERS:
        raise SystemExit("invalid v2 carrier manifest")
    documents = {}
    for name, digest in manifest["files"].items():
        content = (directory / name).read_bytes()
        if hashlib.sha256(content).hexdigest() != digest:
            raise SystemExit(f"stale snapshot digest: {name}")
        documents[name] = json.loads(content)
    operations = documents["operations.json"]
    if not isinstance(operations, list) or not operations or any(row.get("version") != "v2" for row in operations):
        raise SystemExit("all published operations must use major v2")
    return manifest, documents


def local_url(base: str, path: str) -> str:
    parsed = urlsplit(path)
    if (not path.startswith("/") or path.startswith("//") or parsed.scheme or parsed.netloc
            or parsed.fragment or "\\" in path or any(ord(char) < 32 for char in path)):
        raise SystemExit("expected a same-origin absolute path")
    return base.rstrip("/") + path


def mount(openapi: dict) -> str:
    servers = openapi.get("servers", [])
    if len(servers) != 1:
        raise SystemExit("expected the published canonical v2 API mount")
    url = local_url("", servers[0]["url"])
    if not url.endswith("/v2") or urlsplit(url).query:
        raise SystemExit("published API mount is not v2")
    return url


def operation_url(base: str, prefix: str, path: str) -> str:
    return local_url(base, prefix + local_url("", path))


class RefuseRedirects(HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, new_url):
        return None


def open_request(request: Request):
    token = os.environ.get("COREX_CLIENT_TOKEN")
    if token:
        request.add_header("Authorization", "Bearer " + token)
    return build_opener(RefuseRedirects()).open(request, timeout=10)
