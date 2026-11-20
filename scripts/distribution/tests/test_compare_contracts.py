import hashlib
import json
from pathlib import Path
import subprocess
from tempfile import TemporaryDirectory
import unittest


SCRIPT = Path(__file__).resolve().parents[1] / "compare_contracts.py"
DECISIONS = "docs/architecture/corex-2.md"
SNAPSHOT = "packages/core/resources/contracts/php-api.json"
SPI_SOURCE = "packages/core/src/Extension.php"
TEST = "tests/ExtensionTest.php"
EXTENSION = r"CoreX\Extension"
CONSUMER = r"CoreX\Consumer"
MODES = r"CoreX\Mode"


def method(*parameters, **overrides):
    return {
        "visibility": "public", "static": False, "final": False, "abstract": False,
        "by_reference": False, "parameters": list(parameters), "return": "void", "doc": [],
    } | overrides


def parameter(name, *, optional=False, default=None):
    return {
        "name": name, "type": "string", "by_reference": False, "variadic": False,
        "optional": optional, "default": default, "flags": 0,
    }


def symbol(kind, methods, **overrides):
    return {
        "kind": kind, "layer": "api", "since": "1.0", "final": False, "readonly": False,
        "abstract": False, "extends": [], "implements": [], "uses": [], "doc": [],
        "methods": methods, "properties": {}, "constants": [], "enum_backing": None, "enum_cases": [],
    } | overrides


class CompareContractsTest(unittest.TestCase):
    def setUp(self):
        temporary = TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.repo = Path(temporary.name)
        self.git("init", "-q")
        self.git("config", "user.name", "Contract comparison")
        self.git("config", "user.email", "contract-test@example.test")
        self.write(TEST, "<?php\n")
        self.write(SPI_SOURCE, "<?php namespace CoreX; interface Extension { public function run(string $entry): void; }\n")
        self.decisions([])
        self.snapshot()

    def write(self, path, contents):
        target = self.repo / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(contents, encoding="utf-8")

    def write_json(self, path, value):
        self.write(path, json.dumps(value))

    def decisions(self, decisions):
        self.write(DECISIONS, "# CoreX 2\n\n```corex-contract-decisions\n" + json.dumps(decisions) + "\n```\n")

    def snapshot(self, name="entry", **updates):
        symbols = {
            EXTENSION: symbol("interface", {"run": method(parameter(name))}, layer="spi", spi_kind="implement"),
            CONSUMER: symbol("class", {"run": method(parameter("entry"))}),
            MODES: symbol("enum", {}, enum_backing="string", enum_cases=[{"name": "Read", "value": "'read'"}]),
        }
        symbols.update(updates)
        self.write_json(SNAPSHOT, {"schema": "php-api/1", "package": "axiomasoft/corex", "symbols": symbols})

    def git(self, *args):
        return subprocess.check_output(["git", *args], cwd=self.repo, text=True).strip()

    def commit(self, label):
        self.git("add", "--all")
        self.git("commit", "-qm", label)
        return self.git("rev-parse", "HEAD")

    def compare(self, base, candidate, expected_exit):
        result = subprocess.run(
            ["python3", str(SCRIPT), "--base", base, "--candidate", candidate, "--decisions", DECISIONS, "--format", "json"],
            cwd=self.repo / "packages/core", capture_output=True, text=True,
        )
        self.assertEqual(expected_exit, result.returncode, result.stderr or result.stdout)
        return json.loads(result.stdout)

    def test_spi_named_argument_rename_with_refreshed_snapshot_needs_exact_decision(self):
        base = self.commit("Baseline")
        self.write(SPI_SOURCE, "<?php namespace CoreX; interface Extension { public function run(string $message): void; }\n")
        self.snapshot("message")
        candidate = self.commit("Change SPI and refresh its snapshot")
        report = self.compare(base, candidate, expected_exit=1)
        self.assertEqual(1, report["unexplained"])
        delta, = report["changes"]
        self.assertEqual((base, candidate), (report["base"], report["candidate"]))
        self.assertEqual(
            ("php-spi", f"/{EXTENSION}/methods/run/parameters/0/name", ["caller", "implementer"], "entry", "message"),
            (delta["category"], delta["subject"], delta["audiences"], delta["before"], delta["after"]),
        )
        self.decisions([{
            "id": "message-entrypoint",
            "plan": "§11.4 fixture SPI",
            "tests": [TEST],
            "changes": [delta["fingerprint"]],
        }])
        self.snapshot("anotherName")
        self.assertEqual(report, self.compare(base, candidate, expected_exit=1))
        self.snapshot("message")
        approved = self.commit("Document exact transition")
        self.assertEqual("message-entrypoint", self.compare(base, approved, expected_exit=0)["changes"][0]["decision"])

        self.snapshot("anotherName")
        self.write(SPI_SOURCE, "<?php namespace CoreX; interface Extension { public function run(string $anotherName): void; }\n")
        stale = self.compare(base, self.commit("Change approved transition"), expected_exit=1)
        self.assertEqual(["message-entrypoint"], stale["stale_decisions"])
        self.assertEqual(1, stale["unexplained"])

    def test_php_distinguishes_additions_from_caller_and_implementer_breaks(self):
        base = self.commit("Baseline")
        self.snapshot(**{
            EXTENSION: symbol(
                "interface", {"run": method(parameter("entry"), parameter("other", optional=True)), "close": method()},
                layer="spi", spi_kind="implement",
            ),
            CONSUMER: symbol("class", {"run": method(parameter("entry"), parameter("other", optional=True))}),
            MODES: symbol(
                "enum", {}, enum_backing="string",
                enum_cases=[{"name": "Read", "value": "'new'"}, {"name": "Write", "value": "'write'"}],
            ),
        })
        deltas = self.compare(base, self.commit("Change PHP surface"), expected_exit=1)["changes"]
        self.assertEqual(
            {
                (f"/{EXTENSION}/methods/close", "added", True),
                (f"/{EXTENSION}/methods/run/parameters/1", "added", True),
                (f"/{CONSUMER}/methods/run/parameters/1", "added", False),
                (f"/{MODES}/enum_cases/Read", "changed", True),
                (f"/{MODES}/enum_cases/Write", "added", True),
            },
            {(delta["subject"], delta["kind"], delta["breaking"]) for delta in deltas},
        )

    def test_consumers_and_installers_are_compared_from_each_commit(self):
        openapi = "api/v1/openapi.json"
        messages = "packages/core/resources/schemas/message.schema.json"
        routes = "packages/core/routes/api.php"
        config = "packages/core/resources/schemas/config.schema.json"
        self.write_json(openapi, {"paths": {"/things": {"get": {"operationId": "list"}}}})
        self.write_json(messages, {"type": "string"})
        self.write(routes, "<?php Route::get('/things', ThingController::class);")
        self.write_json(config, {"properties": {"enabled": {"default": True}}})
        self.write_json("composer.json", {"require": {"axiomasoft/corex": "^1.0"}})
        base = self.commit("Baseline")
        self.write_json(openapi, {"paths": {"/things": {"post": {"operationId": "create"}}}})
        self.write_json(messages, {"type": "integer"})
        self.write(routes, "<?php Route::post('/things', ThingController::class);")
        self.write_json(config, {"properties": {"enabled": {"default": False}}})
        self.write_json("composer.json", {"require": {"axiomasoft/corex": "^2.0", "other/owner": "^1.0"}})
        report = self.compare(base, self.commit("Change published contracts"), expected_exit=1)
        self.assertEqual(
            {
                ("config-schema", "changed"), ("http-route", "removed"), ("http-route-source", "changed"),
                ("install-edge", "changed"), ("install-edge", "added"), ("message-schema", "changed"),
            },
            {(delta["category"], delta["kind"]) for delta in report["changes"] if delta["breaking"]},
        )
        self.assertEqual(6, report["unexplained"])
        self.assertNotEqual(report["base_digests"][routes], report["candidate_digests"][routes])
        self.assertIn("Route::post('/things'", report["source_diffs"][routes])

    def test_group_approval_is_bound_to_the_exact_breaks(self):
        self.write_json("composer.json", {"require": {"axiomasoft/corex": "^1.0"}})
        base = self.commit("Baseline")
        self.write_json("composer.json", {"require": {"axiomasoft/corex": "^2.0"}})
        candidate = self.commit("Change install edges")
        report = self.compare(base, candidate, expected_exit=1)
        delta, = report["changes"]
        group = hashlib.sha256(json.dumps([delta["fingerprint"]], separators=(",", ":")).encode()).hexdigest()
        self.decisions([{
            "id": "composer-2",
            "plan": "§11.1 Composer cohort",
            "tests": [TEST],
            "group": {"category": "install-edge", "count": 1, "digest": group},
        }])
        candidate = self.commit("Approve exact install changes")
        self.assertEqual("composer-2", self.compare(base, candidate, expected_exit=0)["changes"][0]["decision"])

        self.write_json("composer.json", {"require": {"axiomasoft/corex": "^2.1"}})
        new_candidate = self.commit("Change the approved edge")
        stale = self.compare(base, new_candidate, expected_exit=1)
        self.assertEqual(["composer-2"], stale["stale_decisions"])
        self.assertEqual(1, stale["unexplained"])


if __name__ == "__main__":
    unittest.main()
