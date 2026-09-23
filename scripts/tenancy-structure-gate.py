#!/usr/bin/env python3
"""P9.1 tenancy structure map, compatibility baseline, and red fixtures.

The live tree is not required to already be the 13-context layout.
`check` accepts the committed baseline. `fixture` rejects the two negative trees.
"""
from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
PLAN = REPO / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING"
SRC = REPO / "packages/tenancy/src"
MAP_PATH = PLAN / "findings/P9-tenancy-map.md"
BASELINE_PATH = PLAN / "findings/P9-compatibility-baseline.json"
CATALOG_PATH = PLAN / "findings/P8-binding-catalog.md"
FIXTURE_ROOT = REPO / "tests/fixtures/tenancy-structure"

CANONICAL_ROOTS = (
    "Account",
    "Authentication",
    "Contracts",
    "DomainTrust",
    "Identity",
    "Impersonation",
    "Integration",
    "Laravel",
    "Membership",
    "PHPStan",
    "Provisioning",
    "Runtime",
    "Workspace",
)
STATIONARY_TOPS = frozenset({
    "Contracts",
    "DomainTrust",
    "Identity",
    "Impersonation",
    "Laravel",
    "PHPStan",
    "Provisioning",
})
KIND_RE = re.compile(
    r"^(?:(?:final|abstract|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)",
    re.M,
)
NS_RE = re.compile(r"^namespace ([^;]+);", re.M)


def fail(message: str, code: int = 1) -> None:
    print(message, file=sys.stderr)
    raise SystemExit(code)


def read_text(path: Path) -> str:
    return path.read_text(encoding="utf-8")


def declaration(path: Path) -> tuple[str, str, str]:
    text = read_text(path)
    namespace = NS_RE.search(text)
    kind = KIND_RE.search(text)
    if namespace is None or kind is None:
        fail(f"no namespace/type in {path}")
    fqcn = f"{namespace.group(1)}\\{kind.group(2)}"
    return fqcn, kind.group(1), kind.group(2)


def row(rel: str, dest: str, owner: str, item: str, fqcn: str, kind: str, *, preserve: bool) -> dict:
    after = fqcn if preserve else "CoreX\\Tenancy\\" + dest.removesuffix(".php").replace("/", "\\")
    if dest == rel and after == fqcn:
        reason = "stationary"
    elif after == fqcn:
        reason = "fqcn-preserved"
    else:
        reason = "context-first"
    return {
        "from": rel,
        "to": dest,
        "owner": owner,
        "item": item,
        "fqcn_before": fqcn,
        "fqcn_after": after,
        "kind": kind,
        "compatibility_reason": reason,
    }


def assign(rel: str, fqcn: str, kind: str) -> dict:
    name = Path(rel).name
    top = rel.split("/", 1)[0]
    if rel == "TenancyServiceProvider.php":
        return row(rel, rel, "Laravel", "stationary", fqcn, kind, preserve=True)
    if rel in {"StanclTenancyManager.php", "TenancyManagerContextResolver.php"}:
        return row(rel, f"Runtime/{name}", "Runtime", "P9.2", fqcn, kind, preserve=True)
    if top in STATIONARY_TOPS:
        return row(rel, rel, top, "stationary", fqcn, kind, preserve=True)
    if rel == "Actions/ApplyMembershipProjection.php":
        return row(rel, "Membership/Projection/ApplyMembershipProjection.php", "Membership", "P9.3", fqcn, kind, preserve=False)
    if rel == "Actions/ClaimAccountSignup.php":
        return row(rel, "Provisioning/ClaimAccountSignup.php", "Provisioning", "P9.4", fqcn, kind, preserve=False)
    if top == "Bootstrappers":
        return row(rel, f"Laravel/Bootstrappers/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Central":
        return row(rel, f"Membership/Transport/{name}", "Membership", "P9.3", fqcn, kind, preserve=False)
    if top == "Commands":
        return row(rel, f"Laravel/Commands/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Database":
        preserve = name == "TemplateClonePostgreSQLManager.php"
        return row(rel, f"Provisioning/Database/{name}", "Provisioning", "P9.4", fqcn, kind, preserve=preserve)
    if top == "Events":
        return assign_event(rel, fqcn, kind)
    if top == "Exceptions":
        return assign_exception(rel, fqcn, kind)
    if rel.startswith("Http/Controllers/"):
        return row(rel, f"Authentication/Http/Controllers/{name}", "Authentication", "P9.5", fqcn, kind, preserve=True)
    if rel.startswith("Http/Middleware/"):
        return row(rel, f"Laravel/Http/Middleware/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Jobs":
        return assign_job(rel, fqcn, kind)
    if top == "Lifecycle":
        return row(rel, f"Account/Lifecycle/{name}", "Account", "P9.4", fqcn, kind, preserve=False)
    if top == "Listeners":
        return row(rel, f"Laravel/Listeners/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Migrations":
        return row(rel, f"Provisioning/Migrations/{name}", "Provisioning", "P9.4", fqcn, kind, preserve=False)
    if rel.startswith("Membership/Internal/"):
        group = rel.split("/")[2]
        if group == "Expiry":
            group = "Expiration"
        return row(rel, f"Membership/{group}/{name}", "Membership", "P9.3", fqcn, kind, preserve=False)
    if top == "Models":
        return assign_model(rel, fqcn, kind)
    if top == "Oidc":
        return row(rel, f"Authentication/Oidc/{name}", "Authentication", "P9.5", fqcn, kind, preserve=False)
    if top == "Resolvers":
        return row(rel, f"Laravel/Resolvers/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Scopes":
        return row(rel, f"Laravel/Scopes/{name}", "Laravel", "P9.2", fqcn, kind, preserve=True)
    if top == "Storage":
        return row(rel, f"Runtime/Storage/{name}", "Runtime", "P9.2", fqcn, kind, preserve=False)
    if top == "Support":
        owner = {
            "TenancyOptions.php": "Laravel",
            "AccountLifecyclePolicy.php": "Account",
            "StatusGatePolicy.php": "Account",
            "ImpersonationPolicy.php": "Impersonation",
        }[name]
        return row(rel, f"Laravel/Support/{name}", owner, "P9.2", fqcn, kind, preserve=True)
    if rel.startswith("Wakeup/"):
        return row(rel, f"Integration/Wakeup/{name}", "Integration", "P9.7", fqcn, kind, preserve=False)
    if top == "Workspaces":
        return row(rel, f"Workspace/{name}", "Workspace", "P9.6", fqcn, kind, preserve=False)
    fail(f"unassigned tenancy file {rel}")
    raise AssertionError(rel)


def assign_event(rel: str, fqcn: str, kind: str) -> dict:
    key = rel.removeprefix("Events/").removesuffix(".php")
    targets = {
        "AccountPurged": ("Account/Events", "Account", "P9.4"),
        "AccountSuspended": ("Account/Events", "Account", "P9.4"),
        "ExportReady": ("Account/Events", "Account", "P9.4"),
        "GraceStarted": ("Account/Events", "Account", "P9.4"),
        "TrialStarted": ("Account/Events", "Account", "P9.4"),
        "DomainTrustChanged": ("DomainTrust/Events", "DomainTrust", "P9.6"),
        "ImpersonationEnded": ("Impersonation/Events", "Impersonation", "P9.6"),
        "ImpersonationStarted": ("Impersonation/Events", "Impersonation", "P9.6"),
        "Identity/MembershipGranted": ("Membership/Events", "Membership", "P9.3"),
        "Identity/MembershipRevoked": ("Membership/Events", "Membership", "P9.3"),
        "UserSynced": ("Identity/Events", "Identity", "P9.5"),
    }
    directory, owner, item = targets[key]
    return row(rel, f"{directory}/{Path(rel).name}", owner, item, fqcn, kind, preserve=True)


def assign_exception(rel: str, fqcn: str, kind: str) -> dict:
    name = Path(rel).name
    if name == "OidcAuthenticationException.php":
        return row(rel, f"Authentication/Exceptions/{name}", "Authentication", "P9.5", fqcn, kind, preserve=True)
    if name == "TenantCouldNotBeIdentifiedByHostException.php":
        return row(rel, f"Runtime/Exceptions/{name}", "Runtime", "P9.2", fqcn, kind, preserve=True)
    fail(f"unassigned exception {rel}")
    raise AssertionError(rel)


def assign_job(rel: str, fqcn: str, kind: str) -> dict:
    name = Path(rel).name
    targets = {
        "CreateDatabaseFromTemplate.php": ("Provisioning/Jobs", "Provisioning", "P9.4"),
        "RefreshAccountMembership.php": ("Membership/Jobs", "Membership", "P9.3"),
        "SyncIdentityToAccount.php": ("Identity/Jobs", "Identity", "P9.5"),
    }
    directory, owner, item = targets[name]
    return row(rel, f"{directory}/{name}", owner, item, fqcn, kind, preserve=True)


def assign_model(rel: str, fqcn: str, kind: str) -> dict:
    name = Path(rel).name
    targets = {
        "Account.php": ("Account/Models", "Account", "P9.4"),
        "AccountExport.php": ("Account/Models", "Account", "P9.4"),
        "AccountSchemaState.php": ("Account/Models", "Account", "P9.4"),
        "MigrationBatch.php": ("Provisioning/Models", "Provisioning", "P9.4"),
        "Domain.php": ("DomainTrust/Models", "DomainTrust", "P9.6"),
        "ImpersonationGrant.php": ("Impersonation/Models", "Impersonation", "P9.6"),
        "User.php": ("Identity/Models", "Identity", "P9.5"),
        "Invitation.php": ("Workspace/Models", "Workspace", "P9.6"),
        "Workspace.php": ("Workspace/Models", "Workspace", "P9.6"),
        "WorkspaceMember.php": ("Workspace/Models", "Workspace", "P9.6"),
    }
    directory, owner, item = targets[name]
    return row(rel, f"{directory}/{name}", owner, item, fqcn, kind, preserve=True)


def scan(src: Path = SRC) -> list[dict]:
    rows = []
    for path in sorted(src.rglob("*.php")):
        rel = path.relative_to(src).as_posix()
        fqcn, kind, _leaf = declaration(path)
        rows.append(assign(rel, fqcn, kind))
    return rows


def assert_atomic(rows: list[dict]) -> None:
    sources = [row["from"] for row in rows]
    targets = [row["to"] for row in rows]
    if len(sources) != len(set(sources)) or len(targets) != len(set(targets)):
        duplicate_from = sorted({item for item in sources if sources.count(item) > 1})
        duplicate_to = sorted({item for item in targets if targets.count(item) > 1})
        fail(f"duplicates from={duplicate_from} to={duplicate_to}")
    live = {path.relative_to(SRC).as_posix() for path in SRC.rglob("*.php")}
    if set(sources) != live:
        fail(f"coverage gap missing={sorted(live - set(sources))} extra={sorted(set(sources) - live)}")
    for item in rows:
        if not item["owner"] or not item["item"]:
            fail(f"missing owner/item for {item['from']}")


def quoted(text: str, pattern: str) -> list[str]:
    return re.findall(pattern, text)


def binding_rows() -> list[dict]:
    text = read_text(CATALOG_PATH)
    section = ""
    parsed = []
    lines = text.splitlines()
    index = 0
    while index < len(lines):
        line = lines[index]
        if line.startswith("#"):
            section = line.lstrip("#").strip()
        if line.startswith("| Abstract"):
            index += 2
            while index < len(lines) and lines[index].startswith("|"):
                cells = [cell.strip().strip("`") for cell in lines[index].strip().strip("|").split("|")]
                if len(cells) >= 4:
                    parsed.append({
                        "section": section,
                        "abstract": cells[0],
                        "lifetime": cells[1],
                        "concrete": cells[2],
                        "module": cells[3],
                    })
                index += 1
            continue
        index += 1
    selected = []
    for item in parsed:
        if item["section"].startswith("1.") or item["section"].startswith("2."):
            selected.append(item)
        elif item["section"].startswith("5.") and item["abstract"] != "ModulesOptions":
            selected.append(item)
    if len(selected) < 40:
        fail(f"P8 binding catalog parse returned {len(selected)} tenancy rows")
    return selected


def use_aliases(text: str) -> dict[str, str]:
    aliases = {}
    for item in re.findall(r"^use ([^;]+);", text, re.M):
        item = item.strip()
        if item.startswith(("function ", "const ")):
            continue
        if " as " in item:
            fqcn, name = [part.strip() for part in item.rsplit(" as ", 1)]
        else:
            fqcn, name = item, item.split("\\")[-1]
        aliases[name] = fqcn
    return aliases


def resolve_token(token: str, aliases: dict[str, str]) -> str:
    if "\\" in token:
        head, tail = token.split("\\", 1)
        if head in aliases:
            return aliases[head] + "\\" + tail
    return aliases.get(token, token)


def bootstrapper_order() -> list[str]:
    text = read_text(REPO / "packages/tenancy/config/tenancy.php")
    match = re.search(r"'bootstrapper_order' => \[(.*?)\],", text, re.S)
    if match is None:
        fail("bootstrapper_order missing")
    aliases = use_aliases(text)
    return [resolve_token(token, aliases) for token in re.findall(r"([A-Za-z0-9_\\]+)::class", match.group(1))]


def routes() -> list[dict]:
    text = read_text(REPO / "packages/tenancy/routes/oidc.php")
    found = []
    for match in re.finditer(
        r"Route::(get|post|put|patch|delete)\('([^']+)',\s*\[([A-Za-z0-9_\\]+)::class,\s*'(\w+)'\]\)\s*->name\('([^']+)'\)",
        text,
    ):
        found.append({
            "methods": [match.group(1).upper()],
            "uri": match.group(2),
            "action": match.group(3),
            "action_method": match.group(4),
            "name": match.group(5),
            "middleware": ["tenant"],
        })
    if len(found) != 3:
        fail(f"expected 3 oidc routes, found {len(found)}")
    return found


def middleware_aliases() -> list[dict]:
    text = read_text(REPO / "packages/tenancy/src/Laravel/HttpRegistrar.php")
    return [
        {"alias": alias, "class": class_name}
        for alias, class_name in re.findall(r"aliasMiddleware\('([^']+)',\s*([A-Za-z0-9_\\]+)::class\)", text)
    ]


def command_signatures() -> list[dict]:
    found = []
    for path in sorted(SRC.rglob("*Command.php")):
        text = read_text(path)
        match = re.search(r"protected \$signature = '([^']+)'", text)
        if match is None:
            fail(f"command without signature {path}")
        fqcn, _kind, _leaf = declaration(path)
        found.append({"fqcn": fqcn, "signature": match.group(1), "path": path.relative_to(SRC).as_posix()})
    return found


def listeners() -> list[dict]:
    text = read_text(REPO / "packages/tenancy/src/Laravel/EventsRegistrar.php")
    aliases = use_aliases(text)
    found = [{
        "event": resolve_token("TenantCreated", aliases),
        "listener": resolve_token("JobPipeline", aliases),
        "source": "TenancyOptions.provisioningPipeline",
    }]
    for event, listener in re.findall(r"->listen\(\s*([A-Za-z0-9_\\]+)::class,\s*([A-Za-z0-9_\\]+)::class\)", text):
        found.append({"event": resolve_token(event, aliases), "listener": resolve_token(listener, aliases)})
    for event, listener in re.findall(r"->listen\('([^']+)',\s*\[([A-Za-z0-9_\\]+)::class", text):
        found.append({"event": event, "listener": resolve_token(listener, aliases)})
    if len(found) != 7:
        fail(f"expected 7 listeners, found {len(found)}")
    return found


def models(rows: list[dict]) -> list[dict]:
    found = []
    for item in rows:
        if not item["from"].startswith("Models/"):
            continue
        text = read_text(SRC / item["from"])
        table = re.search(r"protected \$table = '([^']+)'", text)
        connection = re.search(r"protected \$connection = '([^']+)'", text)
        found.append({
            "fqcn": item["fqcn_before"],
            "table": table.group(1) if table else None,
            "connection": connection.group(1) if connection else None,
        })
    return found


def migration_tree_sha256() -> str:
    digest = hashlib.sha256()
    root = REPO / "packages/tenancy/database"
    for path in sorted(root.rglob("*")):
        if not path.is_file():
            continue
        relative = path.relative_to(root).as_posix()
        digest.update(relative.encode())
        digest.update(b"\0")
        digest.update(path.read_bytes())
        digest.update(b"\0")
    return digest.hexdigest()


def composer_discovery() -> dict:
    payload = json.loads(read_text(REPO / "packages/tenancy/composer.json"))
    return {
        "provider": payload["extra"]["laravel"]["providers"][0],
        "public_api": payload["extra"]["corex"]["public-api"],
        "psr4": payload["autoload"]["psr-4"],
    }


def psr4_compatibility(rows: list[dict]) -> list[dict]:
    grouped: dict[str, set[str]] = {}
    for item in rows:
        if item["compatibility_reason"] != "fqcn-preserved":
            continue
        relative = item["fqcn_before"].removeprefix("CoreX\\Tenancy\\").replace("\\", "/") + ".php"
        if relative == item["to"]:
            continue
        prefix_parts = item["fqcn_before"].split("\\")[:-1]
        prefix = "\\".join(prefix_parts) + "\\"
        destination = "src/" + str(Path(item["to"]).parent).replace(".", "")
        if destination == "src/":
            destination = "src/Runtime"
        grouped.setdefault(prefix, set()).add(destination.rstrip("/") or "src/Runtime")
    return [
        {"prefix": prefix, "paths": sorted(paths)}
        for prefix, paths in sorted(grouped.items())
    ]


def coverage(rows: list[dict]) -> dict:
    return {
        "files": len(rows),
        "unique_from": len({row["from"] for row in rows}),
        "unique_to": len({row["to"] for row in rows}),
        "duplicates": 0,
    }


def baseline(rows: list[dict]) -> dict:
    mutable = [
        {"before": row["fqcn_before"], "after": row["fqcn_after"], "from": row["from"]}
        for row in rows
        if row["fqcn_before"] != row["fqcn_after"]
    ]
    public = [
        {"fqcn": row["fqcn_before"], "kind": row["kind"], "path": row["from"]}
        for row in rows
        if row["from"].split("/", 1)[0] in {"Contracts", "Events", "Exceptions", "Models"}
    ]
    discovery = composer_discovery()
    return {
        "schema": "p9-compatibility-baseline/v1",
        "package": "corex/tenancy",
        "allowed_deltas": [
            {
                "id": "D49",
                "kind": "binding-concrete",
                "abstract": "CoreX\\Tenancy\\Contracts\\TenantContextRestorer",
                "lifetime": "scoped",
                "concrete_before": "CoreX\\Tenancy\\GuardedTenantContextRestorer",
                "only_permitted_behavior_delta": True,
            }
        ],
        "bindings": binding_rows(),
        "bootstrapper_order": bootstrapper_order(),
        "double_init_revert": {
            "measured": False,
            "owner": "P9.2",
            "order_source": "packages/tenancy/config/tenancy.php bootstrapper_order",
        },
        "routes": routes(),
        "middleware_aliases": middleware_aliases(),
        "commands": command_signatures(),
        "listeners": listeners(),
        "models": models(rows),
        "migration_tree_sha256": migration_tree_sha256(),
        "discovery": discovery,
        "public_fqcn": public,
        "mutable_internal_fqcn": mutable,
        "psr4_compatibility": psr4_compatibility(rows),
        "coverage": coverage(rows),
        "map": rows,
    }


def render_map(rows: list[dict]) -> str:
    lines = [
        "# P9.1 — карта tenancy",
        "",
        "Post-P8 снимок `packages/tenancy/src`. Каждый PHP-файл имеет один `from`, один `to`, owner и item.",
        "FQCN сохраняется у public/config/queue/Laravel seams. Остальные внутренние namespace меняются вместе с каталогом.",
        "Единственная разрешённая behavioral binding-дельта — concrete `TenantContextRestorer` по D49; она в baseline, не в этой таблице.",
        "",
        "| from | to | owner | item | fqcn_before | fqcn_after | compatibility_reason |",
        "|:--|:--|:--|:--|:--|:--|:--|",
    ]
    for item in rows:
        lines.append(
            "| `{from}` | `{to}` | {owner} | {item} | `{fqcn_before}` | `{fqcn_after}` | {compatibility_reason} |".format(**item)
        )
    lines.append("")
    return "\n".join(lines)


def canonical_baseline(payload: dict) -> str:
    return json.dumps(payload, ensure_ascii=False, indent=2, sort_keys=True) + "\n"


def emit(rows: list[dict]) -> tuple[str, str]:
    assert_atomic(rows)
    return render_map(rows), canonical_baseline(baseline(rows))


def write_outputs(rows: list[dict]) -> None:
    map_text, baseline_text = emit(rows)
    MAP_PATH.parent.mkdir(parents=True, exist_ok=True)
    MAP_PATH.write_text(map_text, encoding="utf-8")
    BASELINE_PATH.write_text(baseline_text, encoding="utf-8")


def check() -> None:
    rows = scan()
    map_text, baseline_text = emit(rows)
    if MAP_PATH.read_text(encoding="utf-8") != map_text:
        fail("map drift")
    if BASELINE_PATH.read_text(encoding="utf-8") != baseline_text:
        fail("baseline drift")
    payload = json.loads(baseline_text)
    if payload["coverage"] != {"files": len(rows), "unique_from": len(rows), "unique_to": len(rows), "duplicates": 0}:
        fail("coverage is not 100% with zero duplicates")
    deltas = payload["allowed_deltas"]
    if len(deltas) != 1 or deltas[0]["id"] != "D49" or deltas[0]["only_permitted_behavior_delta"] is not True:
        fail("D49 is not the only permitted behavior delta")
    print(f"baseline accepted files={len(rows)} duplicates=0")


def top_directories(src: Path) -> list[str]:
    return sorted(path.name for path in src.iterdir() if path.is_dir())


def structure_codes(src: Path) -> list[str]:
    codes = []
    for path in src.rglob("*"):
        parts = path.relative_to(src).parts
        if "Membership" in parts and "Internal" in parts:
            codes.append("membership-internal")
            break
    present = top_directories(src)
    extra = [name for name in present if name not in CANONICAL_ROOTS]
    missing = [name for name in CANONICAL_ROOTS if name not in present]
    if extra:
        codes.append("fourteenth-root")
    elif missing:
        codes.append("incomplete-root")
    return codes


def fixture(name: str) -> None:
    expected = {
        "fourteenth-root": ["fourteenth-root"],
        "membership-internal": ["membership-internal"],
    }
    if name not in expected:
        fail(f"unknown fixture {name}")
    src = FIXTURE_ROOT / name / "src"
    if not src.is_dir():
        fail(f"missing fixture {src}")
    codes = structure_codes(src)
    if codes != expected[name]:
        fail(f"fixture {name} codes {codes} != {expected[name]}")
    print(f"fixture {name} rejected: {codes[0]}")
    raise SystemExit(1)


def main(argv: list[str]) -> None:
    command = argv[1] if len(argv) > 1 else "check"
    if command == "emit":
        write_outputs(scan())
        print(f"wrote {MAP_PATH}")
        print(f"wrote {BASELINE_PATH}")
        return
    if command == "check":
        check()
        return
    if command == "fixture" and len(argv) == 3:
        fixture(argv[2])
        return
    fail("usage: tenancy-structure-gate.py [check|emit|fixture <name>]")


if __name__ == "__main__":
    main(sys.argv)
