#!/usr/bin/env python3
"""Compare published contract carriers from two immutable CoreX Git revisions."""

from __future__ import annotations

import argparse
from collections import defaultdict
from dataclasses import asdict, dataclass, replace
from difflib import unified_diff
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys


class ContractError(ValueError):
    pass


def git(*args, ok=(0,)):
    result = subprocess.run(["git", *args], capture_output=True, check=False)
    if result.returncode not in ok:
        raise ContractError(result.stderr.decode(errors="replace").strip())
    return result.stdout


class Revision:
    def __init__(self, ref):
        self.sha = git("rev-parse", "--verify", "--end-of-options", f"{ref}^{{commit}}").decode().strip()
        self.files = set(git("ls-tree", "--full-tree", "-r", "--name-only", "-z", self.sha).decode().split("\0")) - {""}
        self.digests = {}

    def matching(self, pattern):
        return (path for path in sorted(self.files) if re.fullmatch(pattern, path))

    def read(self, path):
        data = git("show", f"{self.sha}:{path}")
        self.digests[path] = hashlib.sha256(data).hexdigest()
        return data.decode()

    def document(self, path):
        try:
            return json.loads(self.read(path))
        except json.JSONDecodeError as error:
            raise ContractError(f"{self.sha}:{path}: {error}") from error

    def source(self, expression):
        matches = git(
            "grep", "-l", "-z", "-E", expression, self.sha, "--",
            ":(top,glob)packages/*/src/**/*.php", ":(top,glob)products/*/src/**/*.php",
            ok=(0, 1),
        )
        return {entry.split(":", 1)[1] for entry in matches.decode().split("\0") if entry}


def pointer(*parts):
    return "/" + "/".join(str(part).replace("~", "~0").replace("/", "~1") for part in parts)


def fingerprint(value):
    canonical = json.dumps(value, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    return hashlib.sha256(canonical.encode()).hexdigest()


MISSING = object()
PACKAGE = r"(?:packages|products)/[^/]+"
PHP_SNAPSHOT = PACKAGE + r"/resources/contracts/php-api\.json"
HTTP_METHODS = {"delete", "get", "head", "options", "patch", "post", "put", "trace"}
DEPENDENCIES = ("require", "require-dev", "conflict", "provide", "replace")
ROUTE_SOURCE = r"Route::(any|delete|domain|fallback|get|match|middleware|options|patch|post|prefix|put|redirect|resource|view)\("
MESSAGE_SOURCE = r"new[[:space:]]+(MessageContract|MessageEnvelope|MessagePayload)[[:space:]]*\("
DECISION_BLOCK = re.compile(r"^```corex-contract-decisions\n(.*?)^```[ \t]*$", re.MULTILINE | re.DOTALL)


@dataclass(frozen=True)
class Change:
    category: str
    subject: str
    kind: str
    before: object
    after: object
    breaking: bool
    source: str
    audiences: tuple[str, ...] = ()
    decision: str | None = None

    @property
    def fingerprint(self):
        return fingerprint([self.category, self.subject, self.kind, self.before, self.after, self.audiences])


def fields(before, after):
    for key in sorted(before.keys() | after.keys()):
        yield key, before.get(key, MISSING), after.get(key, MISSING)


def change(output, category, subject, before, after, source, *, breaking=None, audiences=()):
    if before is not MISSING and after is not MISSING and fingerprint(before) == fingerprint(after):
        return
    kind = "added" if before is MISSING else "removed" if after is MISSING else "changed"
    output.append(Change(
        category, subject, kind, None if before is MISSING else before, None if after is MISSING else after,
        kind != "added" if breaking is None else breaking, source, audiences,
    ))


def keyed(records, key):
    indexed = {}
    for record in records:
        value = record[key]
        if value in indexed:
            raise ContractError(f"duplicate {key}: {value}")
        indexed[value] = record
    return indexed


def php_symbols(revision):
    symbols = {}
    for path in revision.matching(PHP_SNAPSHOT):
        snapshot = revision.document(path)
        if snapshot["schema"] != "php-api/1":
            raise ContractError(f"{revision.sha}:{path}: unsupported PHP snapshot schema")
        for name, symbol in snapshot["symbols"].items():
            if name in symbols:
                raise ContractError(f"{revision.sha}:{path}: duplicate PHP symbol {name}")
            symbol = symbol.copy()
            layer = symbol["layer"]
            if layer == "spi":
                layer = f"spi-{symbol.pop('spi_kind')}"
            if layer not in {"api", "spi-call", "spi-implement"}:
                raise ContractError(f"{revision.sha}:{path}: unsupported PHP contract layer {layer}")
            symbols[name] = (path, snapshot["package"], symbol | {"layer": layer})
    if not symbols:
        raise ContractError(f"{revision.sha}: PHP contract snapshots are empty")
    return symbols


def php_method(record, subject, before, after, symbol, implementer):
    if before is MISSING or after is MISSING:
        required_constructor = before is MISSING and subject.endswith("/__construct") and any(
            not parameter["optional"] for parameter in after["parameters"]
        )
        added_obligation = before is MISSING and implementer and (symbol["kind"] == "interface" or after["abstract"])
        record(subject, before, after, breaking=after is MISSING or required_constructor or added_obligation)
        return

    for key, old, new in fields(before, after):
        if key != "parameters":
            record(f"{subject}{pointer(key)}", old, new)
            continue
        for index in range(max(len(old), len(new))):
            param_subject = f"{subject}{pointer('parameters', index)}"
            if index >= len(old):
                record(param_subject, MISSING, new[index], breaking=implementer or not new[index]["optional"])
            elif index >= len(new):
                record(param_subject, old[index], MISSING)
            else:
                for field, previous, current in fields(old[index], new[index]):
                    optional_extension = (
                        not implementer and not old[index]["optional"] and new[index]["optional"]
                        and field in {"optional", "default"}
                    )
                    record(f"{param_subject}{pointer(field)}", previous, current, breaking=not optional_extension)


def compare_php(base, candidate, output):
    for name, before, after in fields(php_symbols(base), php_symbols(candidate)):
        source, package, symbol = before if before is not MISSING else after
        category = "php-api" if symbol["layer"] == "api" else "php-spi"
        implementer = symbol["layer"] == "spi-implement"
        audiences = ("caller", "implementer") if implementer else ("caller",)

        def record(subject, old, new, *, breaking=None):
            change(output, category, subject, old, new, source, breaking=breaking, audiences=audiences)

        if before is MISSING or after is MISSING:
            record(pointer(name), MISSING if before is MISSING else symbol, MISSING if after is MISSING else symbol)
            continue
        record(pointer(name, "package"), package, after[1])
        for key, old, new in fields(symbol, after[2]):
            subject = pointer(name, key)
            if key == "methods":
                for member, previous, current in fields(old or {}, new or {}):
                    php_method(record, f"{subject}{pointer(member)}", previous, current, symbol, implementer)
            elif key in {"properties", "constants", "enum_cases"}:
                if key == "enum_cases":
                    old, new = keyed(old, "name"), keyed(new, "name")
                    if old.keys() == new.keys():
                        record(pointer(name, "enum_order"), list(old), list(new))
                if key == "constants":
                    old, new = keyed(old, "name"), keyed(new, "name")
                for member, previous, current in fields(old or {}, new or {}):
                    added_obligation = key == "properties" and implementer
                    exact_case_set = key == "enum_cases"
                    record(
                        f"{subject}{pointer(member)}", previous, current,
                        breaking=previous is not MISSING or added_obligation or exact_case_set,
                    )
            else:
                record(subject, old, new, breaking=key != "since")


def documents(revision):
    """Yield category, subject, value, and source for non-PHP contracts."""
    for path in revision.matching(r"api/v[0-9]+/[^/]+\.json"):
        stem = path.rsplit("/", 1)[0]
        filename = path.rsplit("/", 1)[1]
        snapshot = revision.document(path)
        if filename == "manifest.json":
            # The manifest hashes the individual carriers that are compared below.
            continue
        if filename == "openapi.json":
            for route, declaration in snapshot["paths"].items():
                context = {key: value for key, value in declaration.items() if key not in HTTP_METHODS}
                for method in sorted(HTTP_METHODS & declaration.keys()):
                    yield "http-route", stem + pointer(method.upper(), route), [context, declaration[method]], path
            for key, value in snapshot.items():
                if key != "paths":
                    yield "http-schema", stem + pointer(key), value, path
        elif filename == "operations.json":
            for name, declaration in keyed(snapshot, "id").items():
                yield "http-schema", path + pointer(name), declaration, path
        elif filename == "errors.json":
            by_code = defaultdict(list)
            for declaration in snapshot:
                by_code[declaration["code"]].append(declaration)
            for code, declarations in by_code.items():
                yield "http-schema", path + pointer(code), sorted(declarations, key=fingerprint), path
        else:
            category = "message-schema" if re.search(r"message|event|webhook|envelope", filename) else "http-schema"
            for key, value in snapshot.items() if isinstance(snapshot, dict) else [("schema", snapshot)]:
                yield category, path + pointer(key), value, path

    for path in revision.matching(PACKAGE + r"/resources/contracts/error-codes\.json"):
        snapshot = revision.document(path)
        for name, declaration in keyed(snapshot["codes"], "code").items():
            yield "error", pointer(snapshot["package"], name), declaration, path

    config_ref = "docs/architecture/configuration.md"
    if config_ref in revision.files:
        for row in revision.read(config_ref).splitlines():
            if not row.startswith("| ") or row.startswith("| key |"):
                continue
            columns = [part.strip() for part in re.split(r"(?<!\\)\|", row)[1:-1]]
            if len(columns) != 11:
                raise ContractError(f"{revision.sha}:{config_ref}: invalid configuration row: {row}")
            key, owner, kind, default, secret, mode, reloadable, validation, _, aliases, _ = columns
            yield "config", pointer(key), [owner, kind, default, secret, mode, reloadable, validation, aliases], config_ref

    for path in revision.matching(PACKAGE + r"/resources/schemas/config\.schema\.json"):
        for subject, definition in config_schema(revision.document(path)):
            yield "config-schema", path + subject, definition, path

    for path in revision.matching(
        PACKAGE + r"/resources/(?:contracts|schemas)/[^/]*(?:message|event|envelope)[^/]*\.json"
        r"|schemas/(?:messages|events)/.+\.json"
    ):
        yield "message-schema", path, revision.document(path), path

    route_sources = revision.source(ROUTE_SOURCE) | set(revision.matching(PACKAGE + r"/routes/.+\.php"))
    message_sources = revision.source(MESSAGE_SOURCE) | set(revision.matching(
        PACKAGE + r"/src/.*/(?:MessageContract|MessageEnvelope|MessagePayload|JsonCloudEventCodec|"
        r"ArrayDataScopeCodec|SqlInbox|SqlOutbox|WebhookPayloadProjector|[^/]*MessageContracts|"
        r"[^/]*MessageTranslator|[^/]*EnvelopeCodec)\.php"
    ))
    config_sources = set(revision.matching(PACKAGE + r"/config/.+\.php"))
    for category, paths in [
        ("http-route-source", route_sources), ("message-source", message_sources), ("config-source", config_sources)
    ]:
        for path in sorted(paths):
            revision.read(path)
            yield category, path, revision.digests[path], path

    for path in revision.matching(r"(?:composer|(?:packages|products|fixtures|tests/Distribution/fixtures)/.+/composer)\.json"):
        manifest = revision.document(path)
        for section in DEPENDENCIES:
            for dependency, constraint in manifest.get(section, {}).items():
                yield "install-edge", path + pointer(section, dependency), constraint, path
        for section in ("name", "type", "version", "minimum-stability", "prefer-stable"):
            if section in manifest:
                yield "install-metadata", path + pointer(section), manifest[section], path
        for section in ("autoload", "extra", "config"):
            for key, value in manifest.get(section, {}).items():
                relevant = section == "autoload" or key in {"branch-alias", "corex", "laravel", "packman", "platform"}
                if relevant:
                    yield "install-metadata", path + pointer(section, key), value, path
        repositories = manifest.get("repositories", [])
        repository_entries = repositories.values() if isinstance(repositories, dict) else repositories
        for repository in repository_entries:
            if isinstance(repository, dict) and repository.get("type") == "path":
                yield "install-metadata", path + pointer("repositories", repository["url"]), repository, path

    for path in revision.matching(r"schemas/profiles/.+\.json"):
        profile = revision.document(path)
        for key, value in profile.items():
            yield "install-profile", path + pointer(key), value, path

    profiles_path = "scripts/distribution/install-profiles.json"
    if profiles_path in revision.files:
        profiles = keyed(revision.document(profiles_path)["profiles"], "id")
        for name, profile in profiles.items():
            yield "install-profile", profiles_path + pointer(name), profile, profiles_path

    sets_path = "scripts/schema-sets.json"
    if sets_path in revision.files:
        sets = keyed(revision.document(sets_path)["sets"], "id")
        for name, schema_set in sets.items():
            yield "schema-set", pointer(name), schema_set, sets_path


def config_schema(document, parts=()):
    node = {key: value for key, value in document.items() if key not in {"properties", "description", "title", "examples"}}
    yield pointer(*parts), node
    for key, value in document.get("properties", {}).items():
        yield from config_schema(value, (*parts, key))


def contract_documents(revision):
    documents_by_key = {}
    for category, subject, value, source in documents(revision):
        key = (category, subject)
        if key in documents_by_key:
            raise ContractError(f"{revision.sha}:{source}: duplicate contract {subject}")
        documents_by_key[key] = (value, source)
    return documents_by_key


def compare_documents(base, candidate, output):
    for key, before, after in fields(contract_documents(base), contract_documents(candidate)):
        category, subject = key
        source = (after if before is MISSING else before)[1]
        breaking = before is MISSING and category == "install-edge" and source in base.files
        change(
            output, category, subject,
            MISSING if before is MISSING else before[0],
            MISSING if after is MISSING else after[0],
            source, breaking=True if breaking else None,
        )


def source_diffs(base, candidate, output):
    for delta in output:
        if not delta.category.endswith("-source"):
            continue
        path = delta.source
        before = base.read(path).splitlines() if path in base.files else []
        after = candidate.read(path).splitlines() if path in candidate.files else []
        yield path, "\n".join(unified_diff(before, after, f"{base.sha}:{path}", f"{candidate.sha}:{path}", lineterm=""))


def approve(output, candidate, path):
    if path not in candidate.files:
        raise ContractError(f"{candidate.sha}:{path}: decisions must exist in the candidate revision")
    blocks = DECISION_BLOCK.findall(candidate.read(path))
    if len(blocks) != 1:
        raise ContractError(f"{path}: expected one corex-contract-decisions block")
    decisions = json.loads(blocks[0])
    approvals = {}
    ids = set()
    stale = []
    declared = {}
    for row in decisions:
        name = row["id"]
        if not isinstance(name, str) or not name or name in ids:
            raise ContractError(f"{path}: invalid or duplicate decision ID: {name}")
        ids.add(name)
        if not isinstance(row["plan"], str) or not row["plan"].startswith("§") or not row["plan"].strip("§ "):
            raise ContractError(f"{path}: missing plan mapping for {name}")
        if not isinstance(row["tests"], list) or not row["tests"] or any(
            not isinstance(test, str) or test not in candidate.files for test in row["tests"]
        ):
            raise ContractError(f"{path}: {name} needs candidate test paths")
        if ("changes" in row) == ("group" in row):
            raise ContractError(f"{path}: {name} needs changes or a group digest")
        declared[name] = {"plan": row["plan"], "tests": row["tests"]}
        if "group" in row:
            group = row["group"]
            digests = sorted(delta.fingerprint for delta in output if delta.breaking and delta.category == group["category"])
            if not digests or len(digests) != group["count"] or fingerprint(digests) != group["digest"]:
                stale.append(name)
                continue
        else:
            digests = row["changes"]
            if not isinstance(digests, list) or not digests:
                raise ContractError(f"{path}: {name} needs contract fingerprints")
        for digest in digests:
            if not isinstance(digest, str) or not re.fullmatch(r"[0-9a-f]{64}", digest) or digest in approvals:
                raise ContractError(f"{path}: malformed or duplicate change fingerprint in {name}: {digest}")
            approvals[digest] = name

    for index, delta in enumerate(output):
        if delta.breaking and delta.fingerprint in approvals:
            output[index] = replace(delta, decision=approvals.pop(delta.fingerprint))
    return declared, sorted(set(stale) | set(approvals.values()))


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base", required=True, help="Git revision of the frozen baseline")
    parser.add_argument("--candidate", required=True, help="Git revision of the candidate")
    parser.add_argument("--decisions", required=True, help="ADR path in the candidate tree")
    parser.add_argument("--format", choices=("text", "json"), default="text")
    args = parser.parse_args(argv)
    try:
        base, candidate = Revision(args.base), Revision(args.candidate)
        decisions = Path(args.decisions)
        if decisions.is_absolute() or ".." in decisions.parts:
            raise ContractError("--decisions must be a repository-relative path")
        output = []
        compare_php(base, candidate, output)
        compare_documents(base, candidate, output)
        declared, stale = approve(output, candidate, decisions.as_posix())
    except (ContractError, KeyError, TypeError, AttributeError, UnicodeError, json.JSONDecodeError) as error:
        print(f"compare-contracts: {error}", file=sys.stderr)
        return 2

    breaks = sum(delta.breaking for delta in output)
    unexplained = sum(delta.breaking and not delta.decision for delta in output)
    if args.format == "json":
        sources = dict(source_diffs(base, candidate, output))
        print(json.dumps({
            "base": base.sha,
            "candidate": candidate.sha,
            "base_digests": base.digests,
            "candidate_digests": candidate.digests,
            "changes": [asdict(delta) | {"fingerprint": delta.fingerprint} for delta in output],
            "decisions": declared,
            "stale_decisions": stale,
            "source_diffs": sources,
            "breaks": breaks,
            "unexplained": unexplained,
        }, indent=2, sort_keys=True, ensure_ascii=False))
    else:
        print(f"CoreX contracts {base.sha}..{candidate.sha}")
        for delta in output:
            decision = delta.decision or ("UNEXPLAINED" if delta.breaking else "compatible")
            audiences = f" ({', '.join(delta.audiences)})" if delta.audiences else ""
            print(f"{decision}: {delta.kind} {delta.category} {delta.subject}{audiences} [{delta.fingerprint}]")
        for name in stale:
            print(f"STALE DECISION: {name}")
        print(f"{len(output)} changes, {breaks} breaking or review required, {unexplained} unexplained")
    return int(unexplained > 0 or bool(stale))


if __name__ == "__main__":
    sys.exit(main())
