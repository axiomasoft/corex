#!/usr/bin/env python3
"""P03 code-facts scanner (stdlib only, deterministic). Run from the repo root:

    python3 plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py

Reads git-tracked files only; writes only next to this script. Facts, not design.
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from collections import Counter, defaultdict
from pathlib import Path

REPO = Path(__file__).resolve().parents[5]
OUT = Path(__file__).resolve().parent
PUBLIC_CATS = ("Contracts", "Data", "Enums", "Events", "Exceptions")
BUCKETS = ("Contracts", "Data", "Enums", "Events", "Exceptions", "Internal", "Support",
           "Models", "Commands", "Jobs", "Concerns", "Http")
EXEMPT_TOPS = ("Laravel", "Testing")
BASE_EXC = {"Exception", "Error", "Throwable", "RuntimeException", "LogicException",
            "InvalidArgumentException", "DomainException", "UnexpectedValueException",
            "OutOfBoundsException", "OutOfRangeException", "LengthException", "RangeException",
            "OverflowException", "UnderflowException", "BadMethodCallException",
            "BadFunctionCallException", "JsonException", "ErrorException"}
MODEL_BASE = {"Model", "BaseModel", "Pivot", "MorphPivot", "Authenticatable"}
FQCN_RE = re.compile(r"\\?(?:CoreX|Flex)(?:\\{1,2}[A-Za-z_]\w*)+")
DECL_RE = re.compile(
    r"^[ \t]*((?:(?:final|abstract|readonly)[ \t]+)*)(class|interface|trait|enum)[ \t]+(\w+)([^{;]*)\{",
    re.M)
NS_RE = re.compile(r"^namespace\s+([^;{\s]+)\s*;", re.M)


def git(*args: str) -> str:
    return subprocess.run(["git", *args], cwd=REPO, check=True, capture_output=True,
                          text=True).stdout


def sh(s: str) -> str:
    return s.replace("\t", " ").replace("\n", " ")


def strip_php(src: str):
    """Return (code_without_comments_keeping_newlines, [(line, string_literal_content)])."""
    out, strings = [], []
    i, n, line = 0, len(src), 1
    while i < n:
        c = src[i]
        if c in "'\"":
            q, j, buf = c, i + 1, []
            start_line = line
            while j < n and src[j] != q:
                if src[j] == "\\" and j + 1 < n:
                    buf.append(src[j:j + 2])
                    line += src[j + 1] == "\n"
                    j += 2
                    continue
                line += src[j] == "\n"
                buf.append(src[j])
                j += 1
            strings.append((start_line, "".join(buf)))
            out.append(src[i:j + 1])
            i = j + 1
        elif src.startswith("/*", i):
            j = src.find("*/", i + 2)
            j = n if j < 0 else j + 2
            seg = src[i:j]
            out.append("\n" * seg.count("\n"))
            line += seg.count("\n")
            i = j
        elif src.startswith("//", i) or (c == "#" and not src.startswith("#[", i)):
            j = src.find("\n", i)
            i = n if j < 0 else j
        else:
            line += c == "\n"
            out.append(c)
            i += 1
    return "".join(out), strings


def parse_uses(code: str):
    """alias -> fqcn for class imports (group use supported)."""
    imports = {}
    for m in re.finditer(r"^use\s+(?!function\b|const\b)([^;]+);", code, re.M):
        body = m.group(1).strip()
        g = re.match(r"([\\\w]+)\\\{(.*)\}$", body, re.S)
        items = ([(g.group(1) + "\\" + p.strip(), None) for p in g.group(2).split(",") if p.strip()]
                 if g else [(body, None)])
        for full, _ in items:
            am = re.match(r"([\\\w]+)(?:\s+as\s+(\w+))?$", full.strip())
            if not am:
                continue
            fq = am.group(1).lstrip("\\")
            imports[am.group(2) or fq.rsplit("\\", 1)[-1]] = fq
    return imports


def resolve(name: str, ns: str, imports: dict) -> str:
    if name.startswith("\\"):
        return name.lstrip("\\")
    first, _, rest = name.partition("\\")
    if first in imports:
        return imports[first] + ("\\" + rest if rest else "")
    return (ns + "\\" if ns else "") + name


def pascal(s: str) -> str:
    return "".join(p.capitalize() for p in re.split(r"[-_]", s))


# ---------------------------------------------------------------- discovery
def discover():
    files = git("ls-files", "packages/*/src/*.php", "products/*/src/*.php").split("\n")
    files = sorted(f for f in files if f)
    pkgs = {}
    for f in files:
        parts = f.split("/")
        d = "/".join(parts[:2])
        if d in pkgs:
            continue
        cj = json.loads((REPO / d / "composer.json").read_text())
        root = next((k for k, v in cj.get("autoload", {}).get("psr-4", {}).items() if v == "src/"), None)
        pkgs[d] = {"name": cj["name"], "dir": d, "ns": root.rstrip("\\"), "product": parts[0] == "products"}
    return files, pkgs


def analyse():
    files, pkgs = discover()
    recs = {}
    for f in files:
        d = "/".join(f.split("/")[:2])
        p = pkgs[d]
        rel = f[len(d) + 5:]  # strip "<dir>/src/"
        segs = rel.split("/")
        raw = (REPO / f).read_text(encoding="utf-8", errors="replace")
        code, strings = strip_php(raw)
        nsm = NS_RE.search(code)
        ns = nsm.group(1) if nsm else ""
        decls = list(DECL_RE.finditer(code))
        imports = parse_uses(code)
        r = {"file": f, "pkg": p["name"], "pdir": d, "rel": rel, "dirs": segs[:-1], "name": segs[-1],
             "ns": ns, "code": code, "strings": strings, "imports": imports, "ndecl": len(decls),
             "kind": "none", "symbol": "", "mods": "", "role": "other", "parent": "", "ifaces": [],
             "top": segs[0] if len(segs) > 1 else "<root>", "depth": len(segs) - 1,
             "methods": [], "unclear": []}
        if decls:
            m = decls[0]
            r["mods"] = "/".join(x for x in m.group(1).split())
            r["kind"], r["symbol"] = m.group(2), m.group(3)
            head = m.group(4)
            em = re.search(r"\bextends\s+([\\\w]+)", head) if r["kind"] != "interface" else None
            if em:
                r["parent"] = resolve(em.group(1), ns, imports)
            im = re.search(r"\bimplements\s+(.+)$", head, re.S)
            if im:
                r["ifaces"] = [resolve(x.strip(), ns, imports) for x in im.group(1).split(",") if x.strip()]
            if r["kind"] == "interface":
                xm = re.search(r"\bextends\s+(.+)$", head, re.S)
                if xm:
                    r["ifaces"] = [resolve(x.strip(), ns, imports) for x in xm.group(1).split(",") if x.strip()]
            body = code[m.end():]
            r["methods"] = re.findall(r"\bfunction\s+&?(\w+)\s*\(", body)
        r["fqcn"] = f"{ns}\\{r['symbol']}" if r["symbol"] else ""
        if r["ndecl"] > 1:
            r["unclear"].append("multiple-symbols")
        if not decls:
            r["unclear"].append("no-symbol")
        exp = p["ns"] + ("\\" + "\\".join(r["dirs"]) if r["dirs"] else "")
        r["expected_ns"] = exp
        r["ns_ok"] = ns == exp
        if not r["ns_ok"]:
            r["unclear"].append("ns!=path")
        recs[f] = r
    by_fqcn = {r["fqcn"]: r for r in recs.values() if r["fqcn"]}
    # roles (with inheritance through inventory)
    def base_role(r, seen=()):
        base = r["parent"].rsplit("\\", 1)[-1]
        if r["kind"] == "interface":
            return "contract"
        if r["kind"] == "enum":
            return "enum"
        if base.endswith("ServiceProvider"):
            return "provider"
        if base in ("Command", "GeneratorCommand"):
            return "command"
        if base in MODEL_BASE:
            return "model"
        if any(i.rsplit("\\", 1)[-1] == "ShouldQueue" for i in r["ifaces"]):
            return "job"
        if base in BASE_EXC or base.endswith("Exception") or base.endswith("Error"):
            return "exception"
        par = by_fqcn.get(r["parent"])
        if par and r["parent"] not in seen:
            pr = base_role(par, seen + (r["parent"],))
            if pr in ("provider", "command", "model", "job", "exception"):
                return pr
        if r["kind"] == "class" and "final" in r["mods"] and "readonly" in r["mods"]:
            if all(m == "__construct" or re.match(r"(get|is|has)[A-Z_]", m) for m in r["methods"]):
                return "data"
        return "other"
    for r in recs.values():
        r["role"] = base_role(r) if r["kind"] != "none" else "other"
        if r["role"] == "other" and r["parent"] and r["parent"] not in by_fqcn and r["kind"] == "class":
            r["unclear"].append("other+external-parent:" + r["parent"].rsplit("\\", 1)[-1])
        if r["role"] == "other" and r["kind"] == "class" and "readonly" in r["mods"]:
            r["unclear"].append("readonly-with-logic(value-object?)")
    return files, pkgs, recs, by_fqcn


# ---------------------------------------------------------------- references
def refs_of(code: str, ns: str, imports: dict, by_fqcn: dict):
    """(resolved symbol set, unresolved FQCN set) from imports, code and string literals."""
    found = set()
    for fq in imports.values():
        found.add(fq)
    body = re.sub(r"^(namespace|use)\s[^;]*;", "", code, flags=re.M)
    for m in FQCN_RE.finditer(body):
        found.add(re.sub(r"\\+", r"\\", m.group(0)).lstrip("\\"))
    ok = {x for x in found if x in by_fqcn}
    bad = {x for x in found if x not in by_fqcn and FQCN_RE.fullmatch(x)}
    return ok, bad


def tsv(path: Path, header, rows):
    lines = ["\t".join(header)] + ["\t".join(sh(str(c)) for c in r) for r in rows]
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def top_class(top: str, pdir: str = "") -> str:
    if pdir == "packages/core" and top == "Data":
        return "module"  # `CoreX\Data` is a kernel module (glossary), not a package-level bucket
    if top == "<root>":
        return "root-file"
    if top == "Laravel":
        return "laravel"
    if top == "Testing":
        return "testing"
    if top in BUCKETS:
        return "bucket"
    return "module"


def is_internal(r) -> bool:
    return "Internal" in r["dirs"]


def main() -> int:
    files, pkgs, recs, by_fqcn = analyse()
    plist = sorted(pkgs.values(), key=lambda p: (p["product"], p["name"]))
    pname = {p["dir"]: p["name"] for p in plist}
    R = [recs[f] for f in files]

    # 1 inventory
    tsv(OUT / "inventory.tsv",
        ["package", "path", "namespace", "symbol", "kind", "modifiers", "role", "top", "depth", "ns_matches_path"],
        sorted([r["pkg"], r["file"], r["ns"], r["symbol"], r["kind"], r["mods"], r["role"], r["top"],
                r["depth"], "yes" if r["ns_ok"] else "no"] for r in R))

    # 2 package-shapes
    tops = defaultdict(lambda: defaultdict(list))
    for r in R:
        tops[r["pdir"]][r["top"]].append(r)
    shape = {}
    md = ["# package-shapes\n", "Класс записи верхнего уровня `src/`: `module` (любой каталог не из списка корзин), "
          "`bucket` (Contracts/Data/Enums/Events/Exceptions/Internal/Support/Models/Commands/Jobs/Concerns/Http), "
          "`laravel`, `testing`, `root-file`. Класс определяется по имени каталога; в колонке `module-detail` — что внутри "
          "(cats = публичные категории, int = Internal, flat = файлы прямо в каталоге, sub = прочие подкаталоги).\n"]
    summary = []
    for p in plist:
        t = tops[p["dir"]]
        cnt = Counter()
        md.append(f"\n## {p['name']} (`{p['dir']}`, `{p['ns']}\\`)\n")
        md.append("| запись | класс | файлов | module-detail |\n|:--|:--|--:|:--|")
        for name in sorted(t):
            cl = top_class(name, p["dir"])
            fl = t[name]
            cnt[cl] += 1
            det = ""
            if cl == "module":
                cats = sorted({r["dirs"][1] for r in fl if len(r["dirs"]) > 1 and r["dirs"][1] in PUBLIC_CATS})
                sub = sorted({r["dirs"][1] for r in fl if len(r["dirs"]) > 1
                              and r["dirs"][1] not in PUBLIC_CATS and r["dirs"][1] != "Internal"})
                flat = sum(1 for r in fl if len(r["dirs"]) == 1)
                det = (f"cats={','.join(cats) or '-'}; int={'yes' if any(r['dirs'][1:2] == ['Internal'] for r in fl) else 'no'}"
                       f"; flat={flat}; sub={','.join(sub) or '-'}")
            md.append(f"| `{name if name != '<root>' else '<root>'}` | {cl} | {len(fl)} | {det} |")
        nmod, nb = cnt["module"], cnt["bucket"]
        verdict = ("modular" if nmod and not nb else "single-feature-in-buckets" if not nmod else "mixed")
        shape[p["dir"]] = verdict
        nfiles = sum(len(v) for v in t.values())
        md.append(f"\nИтого: файлов {nfiles}; module={nmod}, bucket={nb}, laravel={cnt['laravel']}, "
                  f"testing={cnt['testing']}, root-file-групп={cnt['root-file']}. **Вердикт: {verdict}.**")
        summary.append((p["name"], verdict, nfiles, nmod, nb))
    md.insert(2, "## Сводка\n\n| пакет | вердикт | файлов | modules | buckets |\n|:--|:--|--:|--:|--:|\n" +
              "\n".join(f"| {a} | {b} | {c} | {d} | {e} |" for a, b, c, d, e in summary) + "\n")
    (OUT / "package-shapes.md").write_text("\n".join(md) + "\n", encoding="utf-8")

    # references
    edges = []  # (from_rec, to_rec)
    unresolved = Counter()
    for r in R:
        ok, bad = refs_of(r["code"], r["ns"], r["imports"], by_fqcn)
        for x in ok:
            t = by_fqcn[x]
            if t["file"] != r["file"]:
                edges.append((r, t))
        for x in bad:
            unresolved[x] += 1
    intra = [(a, b) for a, b in edges if a["pdir"] == b["pdir"] and a["top"] != b["top"]]

    # 3 intra-refs
    grp = defaultdict(list)
    for a, b in intra:
        grp[(a["pkg"], a["top"], b["top"], is_internal(b))].append((a["file"], b["fqcn"]))
    rows = []
    for k, v in sorted(grp.items()):
        ex = sorted(v)[0]
        rows.append([k[0], k[1], k[2], "yes" if k[3] else "no", len(v), f"{ex[0]} -> {ex[1]}"])
    tsv(OUT / "intra-refs.tsv", ["package", "from_top", "to_top", "to_is_internal", "count", "example"], rows)
    md = ["# intra-refs\n", "Ссылки между разными верхними записями `src/` внутри пакета (count = уникальные пары "
          "файл→символ; учтены `use`, FQCN в коде и строках). Ячейка матрицы — from (строка) → to (столбец); "
          "`n/m` = всего / из них в чужой `Internal`.\n"]
    for p in plist:
        pe = [(a, b) for a, b in intra if a["pdir"] == p["dir"]]
        if not pe:
            md.append(f"\n## {p['name']}\n\nМежзаписных ссылок нет.\n")
            continue
        names = sorted({a["top"] for a, _ in pe} | {b["top"] for _, b in pe})
        tot, ints = Counter(), Counter()
        for a, b in pe:
            tot[(a["top"], b["top"])] += 1
            ints[(a["top"], b["top"])] += is_internal(b)
        md.append(f"\n## {p['name']}\n")
        md.append("| from \\ to | " + " | ".join(f"`{n}`" for n in names) + " |\n|:--|" + "--:|" * len(names))
        for a in names:
            cells = [(f"{tot[(a, b)]}/{ints[(a, b)]}" if tot[(a, b)] else "") for b in names]
            md.append(f"| `{a}` | " + " | ".join(cells) + " |")
    md.append("\n## Все ссылки в чужой `Internal` (другая верхняя запись, тот же пакет)\n")
    foreign = sorted((a["pkg"], a["file"], b["fqcn"], b["file"]) for a, b in intra if is_internal(b))
    md.append(f"Всего: {len(foreign)}.\n\n| пакет | from | to (symbol) |\n|:--|:--|:--|")
    md += [f"| {p} | `{f}` | `{s}` |" for p, f, s, _ in foreign]
    (OUT / "intra-refs.md").write_text("\n".join(md) + "\n", encoding="utf-8")

    # 4 consumers
    cons = defaultdict(set)
    scan_files = git("ls-files", "packages/*/src/*.php", "packages/*/tests/*.php", "packages/*/database/*.php",
                     "packages/*/config/*.php", "packages/*/routes/*.php", "products/*/*.php",
                     "tests/*.php").split("\n")
    for f in sorted(set(x for x in scan_files if x)):
        if f in recs:
            r = recs[f]
            code, imports, ns, cpkg = r["code"], r["imports"], r["ns"], r["pkg"]
            cdir = r["pdir"]
        else:
            parts = f.split("/")
            cdir = "/".join(parts[:2]) if parts[0] in ("packages", "products") else ""
            cpkg = pname.get(cdir) or (json.loads((REPO / cdir / "composer.json").read_text())["name"]
                                       if cdir else "<monorepo:tests>")
            code, _ = strip_php((REPO / f).read_text(encoding="utf-8", errors="replace"))
            nsm = NS_RE.search(code)
            ns, imports = (nsm.group(1) if nsm else ""), parse_uses(code)
        ok, _ = refs_of(code, ns, imports, by_fqcn)
        for x in ok:
            t = by_fqcn[x]
            if t["pdir"] != cdir:
                cons[(t["pkg"], x, cpkg)].add(f)
    tsv(OUT / "consumers.tsv", ["target_package", "symbol", "consumer_package", "count"],
        sorted([k[0], k[1], k[2], len(v)] for k, v in cons.items()))

    # 5 strings-risk
    risk = []
    def ctx(f: str, line: str) -> str:
        if re.search(r"morph", line, re.I):
            return "morph-map"
        if f.endswith("composer.json"):
            return "composer-autoload"
        if f.endswith((".neon", ".neon.dist")):
            return "phpstan-neon"
        if f.startswith("scripts/"):
            return "gate-script"
        if "/config/" in f:
            return "config-class"
        if re.search(r"->(bind|singleton|scoped|instance|alias|extend|when|tag|resolving)\(|\bapp\(", line):
            return "container-binding"
        if re.search(r"dispatch|onQueue|Queueable|ShouldQueue|queue|job", line, re.I):
            return "queue-job"
        if "/database/" in f:
            return "database"
        if f.startswith("tests/") or "/tests/" in f:
            return "test"
        return "other"
    def known(fq: str) -> str:
        if fq in by_fqcn:
            return "symbol"
        pre = fq + "\\"
        if any(k.startswith(pre) or (r["ns"] + "\\").startswith(pre) for k, r in by_fqcn.items()):
            return "namespace"
        return "unknown"
    all_files = [f for f in git("ls-files").split("\n") if f]
    skip = ("plans/", "artifacts/", "audits/", "docs/", "docs-public/", ".ai/", ".github/")
    exts = (".php", ".json", ".neon", ".dist", ".yml", ".yaml", ".xml", ".py", ".sh", ".stub", ".txt", ".toml")
    morph_rx = re.compile(r"morphMap|enforceMorphMap|getMorphClass|morphTo\(|morphMany|morphOne|morphToMany|"
                          r"morphedByMany|->(?:nullable|uuid|ulid|nullableUuid|nullableUlid)?[Mm]orphs\(|"
                          r"['\"]\w*_(?:type|class)['\"]")
    for f in sorted(all_files):
        if f.startswith(skip) or not f.endswith(exts) or f.endswith("composer.lock") or f.startswith("packages/") and "/vendor/" in f:
            continue
        text = (REPO / f).read_text(encoding="utf-8", errors="replace")
        if f.endswith(".php"):
            code, strings = strip_php(text)
            lines = text.split("\n")
            for ln, s in strings:
                for m in FQCN_RE.finditer(s):
                    fq = re.sub(r"\\+", r"\\", m.group(0)).lstrip("\\")
                    lt = lines[ln - 1] if ln - 1 < len(lines) else ""
                    risk.append([f, ln, fq, ctx(f, lt), known(fq), lt.strip()[:110]])
            for i, lt in enumerate(code.split("\n"), 1):
                if morph_rx.search(lt) and (f.startswith(("packages/", "products/", "tests/"))):
                    kind = ("morph-api" if re.search(r"morphMap|enforceMorphMap", lt)
                            else "db-type-column" if re.search(r"orphs\(|_type['\"]|_class['\"]", lt)
                            else "morph-relation")
                    risk.append([f, i, "", kind, "-", lt.strip()[:110]])
        else:
            for i, lt in enumerate(text.split("\n"), 1):
                if lt.lstrip().startswith("#"):
                    continue
                for m in FQCN_RE.finditer(lt):
                    fq = re.sub(r"\\+", r"\\", m.group(0)).lstrip("\\")
                    risk.append([f, i, fq, ctx(f, lt), known(fq), lt.strip()[:110]])
    risk = sorted({tuple(r) for r in risk}, key=lambda r: (r[0], int(r[1]), r[2], r[3]))
    tsv(OUT / "strings-risk.tsv", ["file", "line", "fqcn", "context", "resolves", "text"], risk)

    # 6 violations
    def cat_index(r):
        return [i for i, s in enumerate(r["dirs"]) if s in PUBLIC_CATS]
    viol = defaultdict(lambda: defaultdict(list))
    def L(rule, r, msg):
        viol[rule][r["pkg"]].append((r["file"], msg))
    for r in R:
        top, ds = r["top"], r["dirs"]
        exempt = top in EXEMPT_TOPS
        mod_file = r["name"] == pascal(r["pkg"].split("/")[-1].replace("corex-", "")) + "Module.php"
        if top == "<root>":
            if r["role"] != "provider" and not r["name"].endswith("Module.php"):
                L("L1", r, f"root file {r['role']}")
        elif not exempt and top_class(top, r["pdir"]) == "bucket":
            L("L1", r, f"package-level bucket `{top}`")
        if not exempt:
            for i in cat_index(r):
                if r["pdir"] == "packages/core" and top == "Data" and i == 0:
                    continue
                if i != 1:
                    L("L2", r, f"category `{ds[i]}` at depth {i}")
                    break
        if r["kind"] in ("class", "trait", "none") and r["role"] in ("model", "job", "command", "other") \
                and "Internal" not in ds and "Laravel" not in ds:
            extra = " [in public category dir]" if any(s in PUBLIC_CATS for s in ds) else ""
            L("L3", r, f"role {r['role']} outside Internal/Laravel{extra}")
        if not exempt and "Internal" in ds and ds.index("Internal") >= 2:
            L("L4", r, f"Internal at depth {ds.index('Internal')}")
    for a, b in intra:
        if is_internal(b):
            L("L5", a, f"-> {b['fqcn']}")
    md = ["# violations-now\n", "Гейт D05 на текущем дереве (считает, не чинит). Правила независимы, файл может попасть в несколько.\n",
          "Трактовка: L1 — файл в корне `src/`, не provider и не `*Module.php`, либо файл в каталоге-корзине уровня пакета "
          "(любая из `Contracts|Data|Enums|Events|Exceptions|Internal|Support|Models|Commands|Jobs|Concerns|Http`); "
          "L2 — сегмент публичной категории не на индексе 1 пути (индекс 0 = уровень пакета, ≥2 = глубже модуля); "
          "L3 — по ролям инвентаря; `Laravel`/`Testing` в L1/L2/L4 исключены, в L3 исключены только `Internal`/`Laravel`; "
          "L4 — `Internal` на индексе ≥2 (на индексе 0 это L1); L5 — пара (файл, символ) в чужой верхней записи того же "
          "пакета, цель под `Internal`, включая ссылки из корня/`Laravel` (provider-проводка).\n",
          "## Пакет × правило\n", "| пакет | L1 | L2 | L3 | L3 в public-каталоге | L4 | L5 |\n|:--|--:|--:|--:|--:|--:|--:|"]
    tot = Counter()
    for p in plist:
        c = {k: len(viol[k][p["name"]]) for k in ("L1", "L2", "L3", "L4", "L5")}
        c3 = sum(1 for _, m in viol["L3"][p["name"]] if "public category" in m)
        for k, v in c.items():
            tot[k] += v
        tot["L3c"] += c3
        md.append(f"| {p['name']} | {c['L1']} | {c['L2']} | {c['L3']} | {c3} | {c['L4']} | {c['L5']} |")
    md.append(f"| **всего** | {tot['L1']} | {tot['L2']} | {tot['L3']} | {tot['L3c']} | {tot['L4']} | {tot['L5']} |")
    md.append("\nПродукт (`flex/crm`) — отдельной строкой (N8); правила D05 к нему приложены для справки.\n")
    for rule in ("L1", "L2", "L3", "L4", "L5"):
        md.append(f"\n## {rule} — примеры (до 10, по одному кругу от пакета)\n")
        per = {k: sorted(v) for k, v in viol[rule].items()}
        ex = []
        depth = 0
        while len(ex) < 10 and any(len(v) > depth for v in per.values()):
            for k in sorted(per):
                if len(per[k]) > depth and len(ex) < 10:
                    ex.append((k, *per[k][depth]))
            depth += 1
        md += [f"- `{f}` ({k}) — {m}" for k, f, m in ex] or ["- нет"]
    (OUT / "violations-now.md").write_text("\n".join(md) + "\n", encoding="utf-8")

    # 7 proposals
    single = {d for d, v in shape.items() if v == "single-feature-in-buckets"}
    module_dirs = {d: sorted(n for n in tops[d] if top_class(n, d) == "module") for d in tops}
    # affinity assignment for bucket/root files in non-single packages
    adj = defaultdict(lambda: defaultdict(int))
    for a, b in edges:
        if a["pdir"] == b["pdir"]:
            adj[a["file"]][b["file"]] += 1
            adj[b["file"]][a["file"]] += 1
    assign, why = {}, {}
    for d in sorted(tops):
        if d in single:
            continue
        fallback = pascal(pname[d].split("/")[-1].replace("corex-", "")) if pname[d] != "axiomasoft/corex" else ""
        pf = [r for r in R if r["pdir"] == d]
        mod_of = {r["file"]: r["top"] for r in pf if top_class(r["top"], d) == "module"}
        pending = [r for r in pf if top_class(r["top"], d) in ("bucket", "root-file")
                   and not (r["role"] == "provider" or r["name"].endswith("Module.php"))]
        for r in pending:
            score = Counter()
            for o, w in adj[r["file"]].items():
                if o in mod_of:
                    score[mod_of[o]] += w
            for m in module_dirs[d]:
                if r["name"].startswith(m):
                    score[m] += 2
            if score:
                m, s = sorted(score.items(), key=lambda x: (-x[1], x[0]))[0]
                assign[r["file"]] = m
                why[r["file"]] = ("high" if s >= 3 and s / sum(score.values()) > .6 else "medium",
                                  f"affinity {s}/{sum(score.values())} to {m}")
        for _ in range(5):
            for r in pending:
                if r["file"] in assign:
                    continue
                score = Counter(assign[o] for o in adj[r["file"]] if o in assign)
                if score:
                    m = sorted(score.items(), key=lambda x: (-x[1], x[0]))[0][0]
                    assign[r["file"]] = m
                    why[r["file"]] = ("low", f"propagated via neighbours -> {m}")
        for r in pending:
            if r["file"] not in assign:
                pkgmod = fallback if fallback in module_dirs[d] or fallback else "Core"
                assign[r["file"]] = pkgmod
                why[r["file"]] = ("low", f"no refs to any module; fallback `{pkgmod}`")

    def place(r, scheme: str):
        """-> (new dirs list, module, confidence, note). scheme S1 or S2."""
        d = r["pdir"]
        conf, notes = "high", []
        if r["top"] in EXEMPT_TOPS:
            return r["dirs"], "", "high", "exempt (Laravel/Testing)"
        if r["role"] == "provider" or (r["top"] == "<root>" and r["name"].endswith("Module.php")):
            return r["dirs"], "", "high", "provider/module class stays" + (" (non-root provider)" if r["dirs"] else "")
        cl = top_class(r["top"], d)
        if cl == "module":
            module, inner = r["top"], r["dirs"][1:]
        else:
            inner = r["dirs"]
            if d in single:
                module = "" if scheme == "S1" else pascal(pname[d].split("/")[-1].replace("corex-", ""))
                n = sum(1 for x in R if x["pdir"] == d)
                if scheme == "S2":
                    conf = "high" if n <= 25 else "medium" if n <= 60 else "low"
                    notes.append(f"package-named module; package has {n} files, no sub-feature clustering")
            else:
                module = assign[r["file"]]
                conf, why_ = why[r["file"]]
                notes.append(why_)
        if inner and inner[0] in PUBLIC_CATS + ("Internal",):
            new_inner, conf2 = inner, "high"
        elif inner and inner[0] in EXEMPT_TOPS:
            new_inner, conf2 = inner, "medium"
            notes.append("module-level Laravel/Testing kept")
        else:
            cats = [i for i, s in enumerate(inner) if s in PUBLIC_CATS]
            if cats:
                i = cats[0]
                new_inner = [inner[i], *inner[:i], *inner[i + 1:]]
                conf2 = "medium"
                notes.append("nested category hoisted")
            else:
                rest = [s for s in inner if s not in BUCKETS or s == "Internal"]
                pub = {"contract": "Contracts", "enum": "Enums", "exception": "Exceptions", "data": "Data"}.get(r["role"])
                if pub:
                    new_inner = [pub, *rest]
                    conf2 = "medium" if r["role"] == "data" else "high"
                    if r["role"] == "data":
                        notes.append("data by heuristic")
                else:
                    new_inner = ["Internal", *inner]
                    conf2 = "medium" if r["role"] == "other" and r["kind"] == "class" and "readonly" in r["mods"] else "high"
                    if r["role"] == "other" and r["kind"] == "class":
                        if "readonly" in r["mods"]:
                            notes.append("readonly other: value object?")
                        elif any(s in PUBLIC_CATS for s in inner):
                            conf2 = "medium"
                    if r["role"] == "command":
                        notes.append("command -> Internal (vs Laravel)")
        order = {"high": 0, "medium": 1, "low": 2}
        conf = max(conf, conf2, key=lambda c: order[c])
        return ([module] if module else []) + new_inner, module, conf, "; ".join(notes)

    prop, coll = [], defaultdict(list)
    stats = {s: defaultdict(lambda: [0, 0]) for s in ("S1", "S2")}
    placed = {}
    for r in R:
        p = next(x for x in plist if x["dir"] == r["pdir"])
        row = {}
        for s in ("S1", "S2"):
            nd, mod, conf, note = place(r, s)
            path = "/".join([r["pdir"], "src", *nd, r["name"]])
            ns = p["ns"] + ("\\" + "\\".join(nd) if nd else "")
            row[s] = (path, ns, mod, conf, note, nd)
            coll[(s, path)].append(r["file"])
            st = stats[s][r["pkg"]]
            st[0] += path != r["file"]
            st[1] += bool(r["symbol"]) and ns != r["ns"]
        placed[r["file"]] = row
    for r in R:
        row = placed[r["file"]]
        confs = [row["S1"][3], row["S2"][3]]
        conf = max(confs, key=lambda c: {"high": 0, "medium": 1, "low": 2}[c])
        notes = []
        for s in ("S1", "S2"):
            n = row[s][4]
            if len(coll[(s, row[s][0])]) > 1:
                n = (n + "; " if n else "") + f"COLLISION {s} with " + ",".join(sorted(set(coll[(s, row[s][0])]) - {r["file"]})[:2])
                conf = "low"
            if n and (s == "S2" or n != row["S2"][4]):
                notes.append(f"{s}: {n}")
        prop.append([r["pkg"], r["file"], r["ns"], row["S1"][0], row["S1"][1], row["S2"][0], row["S2"][1],
                     row["S2"][2] or "(package root)", conf, " | ".join(notes)])
    tsv(OUT / "module-proposal.tsv",
        ["package", "path", "current_ns", "s1_path", "s1_ns", "s2_path", "s2_ns", "s2_module", "confidence", "note"],
        sorted(prop))
    colls = {s: sum(1 for (sc, _), v in coll.items() if sc == s and len(v) > 1) for s in ("S1", "S2")}

    # post-scheme checks
    def post(scheme):
        c = Counter()
        for r in R:
            nd = placed[r["file"]][scheme][5]
            top = nd[0] if nd else "<root>"
            if top in EXEMPT_TOPS:
                continue
            if not nd:
                continue
            if top_class(top, r["pdir"]) == "bucket":
                c["L1(pkg-bucket)"] += 1
            cats = [i for i, s in enumerate(nd) if s in PUBLIC_CATS]
            if r["pdir"] == "packages/core" and top == "Data":
                cats = [i for i in cats if i != 0]
            if cats and cats[0] != 1:
                c["L2"] += 1
            if "Internal" in nd and nd.index("Internal") >= 2:
                c["L4"] += 1
        # L5 by new module
        for a, b in intra:
            pass
        mod = lambda x: (placed[x["file"]][scheme][5] or ["<root>"])[0]
        for a, b in edges:
            if a["pdir"] == b["pdir"] and mod(a) != mod(b) and "Internal" in placed[b["file"]][scheme][5][:2] \
                    and mod(a) not in EXEMPT_TOPS and mod(a) != "<root>":
                c["L5(cross-module ref to Internal)"] += 1
        return c
    post1, post2 = post("S1"), post("S2")

    # 8 README
    rc = Counter(r["role"] for r in R)
    kc = Counter(r["kind"] for r in R)
    n_ns_bad = sum(not r["ns_ok"] for r in R)
    unclear = sorted((r["file"], "; ".join(r["unclear"])) for r in R
                     if r["unclear"] and any(u.split(":")[0] in ("multiple-symbols", "no-symbol", "ns!=path",
                                                                "readonly-with-logic(value-object?)") for u in r["unclear"]))
    ext_parent = sorted((r["file"], r["parent"]) for r in R if any(u.startswith("other+external-parent") for u in r["unclear"]))
    (OUT / "unclassified.tsv").write_text(
        "file\treason\n" + "\n".join(f"{f}\t{u}" for f, u in sorted(set(unclear) | {(f, "other+external-parent:" + p) for f, p in ext_parent}))
        + "\n", encoding="utf-8")
    ncons = len(cons)
    rk = Counter(r[3] for r in risk)
    md = ["# P03 cloud facts — README\n", "Только факты кода; решений здесь нет. Дерево на момент скана: HEAD ветки от `main`.\n",
          "## Воспроизведение\n", "```bash\npython3 plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py\n```\n",
          "Запуск из корня репо, stdlib, детерминированный (сортировка, только git-tracked). "
          "Файлы: `inventory.tsv`, `package-shapes.md`, `intra-refs.{tsv,md}`, `consumers.tsv`, `strings-risk.tsv`, "
          "`violations-now.md`, `module-proposal.tsv`, `unclassified.tsv`.\n",
          "## Сводные числа\n",
          f"1. **Инвентарь:** {len(R)} PHP-файлов в области ({len(pkgs)} каталогов: {len(pkgs) - 1} пакетов + 1 продукт). "
          f"kind: " + ", ".join(f"{k}={v}" for k, v in sorted(kc.items())) + ". role: " + ", ".join(f"{k}={v}" for k, v in sorted(rc.items()))
          + f". Namespace ≠ путь (N4) в {n_ns_bad} файлах.",
          "2. **Формы пакетов:** " + "; ".join(f"{n}: {v}" for n, v, *_ in summary) + ".",
          f"3. **Внутрипакетные ссылки между верхними записями:** {len(intra)} пар файл→символ; из них в чужой `Internal` — {len(foreign)}. "
          f"Неразрешённых `CoreX\\…/Flex\\…` имён (namespace-импорты, удалённые/строковые фрагменты): {len(unresolved)} различных.",
          f"4. **Потребители:** {ncons} троек (пакет-цель, символ, потребитель); "
          f"уникальных символов с внешними потребителями: {len({(k[0], k[1]) for k in cons})}.",
          f"5. **Риск строк/не-PHP:** {len(risk)} строк; по контексту: " + ", ".join(f"{k}={v}" for k, v in sorted(rk.items())) + ".",
          f"6. **Гейт D05 сейчас:** L1={tot['L1']}, L2={tot['L2']}, L3={tot['L3']} (из них в public-каталоге {tot['L3c']}), "
          f"L4={tot['L4']}, L5={tot['L5']} (см. `violations-now.md`).",
          "7. **Переносы (файлов / классов с изменением FQCN):**\n",
          "| пакет | вердикт | файлов | S1 файлов | S1 FQCN | S2 файлов | S2 FQCN |\n|:--|:--|--:|--:|--:|--:|--:|"]
    for p in plist:
        n = sum(1 for r in R if r["pdir"] == p["dir"])
        md.append(f"| {p['name']} | {shape[p['dir']]} | {n} | {stats['S1'][p['name']][0]} | {stats['S1'][p['name']][1]} | "
                  f"{stats['S2'][p['name']][0]} | {stats['S2'][p['name']][1]} |")
    for s in ("S1", "S2"):
        md.append("")
    md.append(f"| **всего** | | {len(R)} | {sum(v[0] for v in stats['S1'].values())} | {sum(v[1] for v in stats['S1'].values())} | "
              f"{sum(v[0] for v in stats['S2'].values())} | {sum(v[1] for v in stats['S2'].values())} |\n")
    cc = Counter(p[8] for p in prop)
    md.append(f"Уверенность (макс. из S1/S2 на файл): " + ", ".join(f"{k}={cc[k]}" for k in ("high", "medium", "low"))
              + f". Коллизии целевого пути: S1={colls['S1']}, S2={colls['S2']}.\n")
    md.append("Самопроверка предложений по правилам гейта (число файлов): "
              f"S1 -> {dict(post1) or 'нарушений L1/L2/L4/L5 нет'}; S2 -> {dict(post2) or 'нарушений L1/L2/L4/L5 нет'}. "
              "(L1 для S1 включает корзины уровня пакета single-feature пакетов — это следствие самого варианта S1.)\n")
    md.append("## Допущения эвристик\n")
    md += ["- Роль `data`: `final readonly class` только с `__construct` и `get*/is*/has*`; readonly-класс с фабриками/логикой — `other`.",
           "- Роль наследуется по цепочке внутри инвентаря; внешние родители распознаются по имени (`*Exception`, `Model`, `Command`, `*ServiceProvider`).",
           "- Ссылки: `use` (в т.ч. group), FQCN в коде и строках; относительные имена без `use` и namespace-импорты не разрешаются.",
           "- Комментарии вырезаются конечным автоматом (heredoc не разбирается).",
           "- Предложения: категории по роли (interface→Contracts, enum→Enums, exception→Exceptions, data→Data, остальное→`Internal`); "
           "каталог с публичной категорией в пути побеждает роль; команды идут в `Internal`; provider/`*Module.php`/`Laravel`/`Testing` не двигаются.",
           "- S1/S2 для пакетов `modular`/`mixed` совпадают (модули уже есть); корзины `mixed`-пакетов раскладываются по модулям по сродству ссылок."]
    md.append("\n## Неясности (эвристика не классифицировала)\n")
    md.append(f"Пофайловый список: `unclassified.tsv` ({len(set(unclear) | set(ext_parent))} строк). Кратко по причинам: " +
              ", ".join(f"{k}={v}" for k, v in sorted(Counter(u.split(':')[0] for _, us in unclear for u in us.split('; ')).items())) +
              f"; other+внешний родитель={len(ext_parent)}.\n")
    small = [(f, u) for f, u in unclear if "multiple-symbols" in u or "no-symbol" in u]
    md += [f"- `{f}` — {u}" for f, u in small] or ["- файлов без символа/с несколькими символами нет"]
    md.append("\n## Вопросы к дизайну (без ответов)\n")
    qs = ["S1 оставляет корзины уровня пакета у single-feature пакетов — как это совместимо с L1/L2 и запретом корзин в D05 (нужен ли неявный модуль в гейте)?",
          "L3 бьёт по `other`-классам в публичных каталогах (`Events`, `Data`): нужна ли роль `event`/`value-object` или правило уточняется?",
          "Куда командам: `Laravel\\Commands` (D05 говорит «Laravel-мост … commands») или `Internal`? Сейчас в предложении — `Internal`.",
          "Имя модуля single-feature пакета при S2: имя пакета даёт `CoreX\\Webhooks\\Webhooks\\…`; нужна ли кластеризация по подфичам (Delivery/Subscription…)?",
          "Крупные «модули» (`core/Modules` 105 файлов, `tenancy/Membership` 55, `core/Messaging` 44): делить ли подкаталоги на самостоятельные модули?",
          "Подкаталоги внутри модуля вне категорий (напр. `Modules/Compiler/…`): сворачивать в `Internal\\<Sub>` (как в предложении) или сохранять?",
          "Провайдеры внутри модулей (не в корне) и `PHPStan/`-каталог tenancy: где должны жить по D05?",
          "Ссылки корня/`Laravel` (provider-проводка) в `Internal` модулей — считаются ли нарушением L5?",
          "Строки-FQCN (morph map, config class, binding, queue): какие из `strings-risk.tsv` хранятся в БД и требуют миграции/алиаса?",
          "Файл `tests/` и `database/` потребителей внешних пакетов: переносятся ли они тем же инструментом (N9)?"]
    md += [f"{i}. {q}" for i, q in enumerate(qs, 1)]
    (OUT / "README.md").write_text("\n".join(md) + "\n", encoding="utf-8")
    print(f"ok: {len(R)} files, {len(intra)} intra edges, {ncons} consumer rows, {len(risk)} risk rows")
    return 0


if __name__ == "__main__":
    sys.exit(main())
