"""Schema, render stability and planted-negative proof for DoD 1.0."""

from __future__ import annotations

import importlib.util
import json
import subprocess
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts/dod-1.0.py"
FIXTURE = ROOT / "tests/fixtures/dod-1.0"
SPEC = importlib.util.spec_from_file_location("dod_1_0", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)


def load(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def head() -> str:
    return subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()


class DodSchemaTest(unittest.TestCase):
    def test_production_manifest_has_27_contiguous_ids(self) -> None:
        manifest = MODULE.load_manifest(ROOT / "scripts/dod-1.0.json", ROOT)
        self.assertEqual([row["id"] for row in manifest["assertions"]], list(MODULE.EXPECTED_IDS))

    def test_render_is_byte_stable(self) -> None:
        manifest = MODULE.load_manifest(ROOT / "scripts/dod-1.0.json", ROOT)
        document = ROOT / "docs/architecture/dod-1.0.md"
        self.assertEqual(document.read_text(encoding="utf-8"), MODULE.render_markdown(manifest))

    def test_26_row_manifest_fails(self) -> None:
        with self.assertRaises(MODULE.DodError) as caught:
            MODULE.validate_manifest(load(FIXTURE / "manifest-26.json"), ROOT)
        self.assertIn("expected 27", str(caught.exception))

    def test_duplicate_id_fails(self) -> None:
        document = load(ROOT / "scripts/dod-1.0.json")
        document["assertions"][1]["id"] = "DOD-01"
        with self.assertRaises(MODULE.DodError) as caught:
            MODULE.validate_manifest(document, ROOT)
        self.assertIn("duplicate ID", str(caught.exception))

    def test_ownerless_row_fails(self) -> None:
        document = load(ROOT / "scripts/dod-1.0.json")
        document["assertions"][0]["owner"] = []
        with self.assertRaises(MODULE.DodError) as caught:
            MODULE.validate_manifest(document, ROOT)
        self.assertIn("ownerless", str(caught.exception))

    def test_both_evidence_modes_fail(self) -> None:
        document = load(ROOT / "scripts/dod-1.0.json")
        document["assertions"][0]["command"] = ["php", "scripts/package-docs-gate.php"]
        with self.assertRaises(MODULE.DodError) as caught:
            MODULE.validate_manifest(document, ROOT)
        self.assertIn("both evidence modes", str(caught.exception))

    def test_hand_edited_document_fails_check(self) -> None:
        manifest = MODULE.load_manifest(ROOT / "scripts/dod-1.0.json", ROOT)
        rendered = MODULE.render_markdown(manifest)
        mutated = rendered.replace("DOD-01", "DOD-00", 1)
        target = FIXTURE / "hand-edited.md"
        try:
            target.write_text(mutated, encoding="utf-8")
            code = MODULE.main(["render", "--check", "--output", str(target)])
            self.assertEqual(code, 1)
        finally:
            target.unlink(missing_ok=True)

    def test_scorecard_keeps_producer_doc_failures_red(self) -> None:
        receipt = load(ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P17-scorecard-docs-receipt.json")
        manifest = MODULE.load_manifest(ROOT / "scripts/dod-1.0.json", ROOT)
        binding = {
            "source_commit": receipt["source_commit"],
            "candidate_sha256": receipt["candidate_sha256"],
        }
        by_id = {row["id"]: row for row in manifest["assertions"]}
        purpose, purpose_detail = MODULE.judge_receipt(
            by_id["DOD-01"], receipt, root=ROOT, binding=binding,
            head=receipt["source_commit"], environment={},
        )
        examples, example_detail = MODULE.judge_receipt(
            {"id": "DOD-24", "freshness_inputs": by_id["DOD-01"]["freshness_inputs"]},
            receipt, root=ROOT, binding=binding, head=receipt["source_commit"], environment={},
        )
        self.assertEqual(purpose, "green", purpose_detail)
        self.assertEqual(examples, "red", example_detail)
        self.assertIn("FAIL", example_detail)

    def test_bc_classifier_accepts_experimental_break_and_rejects_weakening(self) -> None:
        ok = "\n".join([
            "bc-check: corex/core (core) |compatible| stability=experimental base=abc1234 candidate=def5678",
            "bc-check: corex/attributes (attributes) |bc-break| stability=experimental base=abc1234 candidate=def5678",
            "bc-check: OK",
        ])
        status, _detail = MODULE.classify_bc_output(ok)
        self.assertEqual(status, "green")
        for sample, expected in (
            ("bc-check: skip — requires PHP 8.4", "skipped"),
            ("bc-check: corex/core (core) |infrastructure| stability=experimental base=abc1234 candidate=def5678", "red"),
            ("bc-check: corex/core (core) |bc-break| stability=stable base=abc1234 candidate=", "red"),
            ("no rows", "red"),
        ):
            with self.subTest(sample=sample):
                seen, _detail = MODULE.classify_bc_output(sample)
                self.assertEqual(seen, expected)

    def test_d107_no_move_receipt_is_green(self) -> None:
        text = (ROOT / "plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P16-profile-a-receipts.md").read_text(encoding="utf-8")
        status, detail = MODULE._judge_d107(text)
        self.assertEqual(status, "green", detail)


class DodPlantTest(unittest.TestCase):
    def setUp(self) -> None:
        self.originals = {
            path: path.read_bytes()
            for path in (
                FIXTURE / "candidate.json",
                FIXTURE / "stale-receipt.json",
                FIXTURE / "profile-receipt.json",
                FIXTURE / "workspace-receipt.json",
            )
        }
        commit = head()
        for name in ("candidate.json", "stale-receipt.json", "profile-receipt.json", "workspace-receipt.json"):
            path = FIXTURE / name
            document = load(path)
            document["source_commit"] = commit
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")

    def tearDown(self) -> None:
        for path, payload in self.originals.items():
            path.write_bytes(payload)
        plant = FIXTURE / "plants"
        for marker in ("missing-api-symbol", "internal-import"):
            (plant / marker).unlink(missing_ok=True)

    def check(self) -> dict:
        manifest = MODULE.load_manifest(FIXTURE / "manifest.json", ROOT)
        return MODULE.check_manifest(
            manifest,
            root=ROOT,
            candidate=FIXTURE / "candidate.json",
            profile="release",
            environment={},
        )

    def status(self, result: dict, identifier: str) -> dict:
        return next(row for row in result["rows"] if row["id"] == identifier)

    def test_clean_fixture_is_27_green(self) -> None:
        result = self.check()
        self.assertEqual(result["summary"]["rows"], 27)
        self.assertTrue(MODULE.result_is_green(result), result["summary"])

    def test_planted_missing_api_symbol_fails_dod_04(self) -> None:
        marker = FIXTURE / "plants/missing-api-symbol"
        try:
            marker.write_text("plant", encoding="utf-8")
            row = self.status(self.check(), "DOD-04")
            self.assertEqual(row["status"], "red")
            self.assertIn("missing API symbol", row["diagnostic"])
        finally:
            marker.unlink(missing_ok=True)
        self.assertTrue(MODULE.result_is_green(self.check()))

    def test_planted_internal_import_fails_dod_05(self) -> None:
        marker = FIXTURE / "plants/internal-import"
        try:
            marker.write_text("plant", encoding="utf-8")
            row = self.status(self.check(), "DOD-05")
            self.assertEqual(row["status"], "red")
            self.assertIn("Internal import", row["diagnostic"])
        finally:
            marker.unlink(missing_ok=True)
        self.assertTrue(MODULE.result_is_green(self.check()))

    def test_planted_stale_receipt_fails_dod_21(self) -> None:
        path = FIXTURE / "stale-receipt.json"
        try:
            document = load(path)
            document["source_commit"] = "0" * 40
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
            row = self.status(self.check(), "DOD-21")
            self.assertEqual(row["status"], "stale")
            self.assertIn("stale receipt", row["diagnostic"])
        finally:
            path.write_bytes(self.originals[path])
            document = load(path)
            document["source_commit"] = head()
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
        self.assertTrue(MODULE.result_is_green(self.check()))

    def test_planted_workspace_autoload_fails_dod_26(self) -> None:
        path = FIXTURE / "workspace-receipt.json"
        try:
            document = load(path)
            document["autoload_workspace"] = str(ROOT / "vendor/autoload.php")
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
            row = self.status(self.check(), "DOD-26")
            self.assertEqual(row["status"], "red")
            self.assertIn("workspace autoload path", row["diagnostic"])
        finally:
            path.write_bytes(self.originals[path])
            document = load(path)
            document["source_commit"] = head()
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
        self.assertTrue(MODULE.result_is_green(self.check()))

    def test_planted_omitted_profile_fails_dod_14(self) -> None:
        path = FIXTURE / "profile-receipt.json"
        try:
            document = load(path)
            document["profiles"] = [item for item in document["profiles"] if item != "search-portable"]
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
            row = self.status(self.check(), "DOD-14")
            self.assertEqual(row["status"], "red")
            self.assertIn("omitted profile", row["diagnostic"])
        finally:
            path.write_bytes(self.originals[path])
            document = load(path)
            document["source_commit"] = head()
            path.write_text(json.dumps(document, indent=2) + "\n", encoding="utf-8")
        self.assertTrue(MODULE.result_is_green(self.check()))

    def test_stale_ci_evidence_is_rejected(self) -> None:
        evidence = {
            "schema_version": MODULE.RESULT_VERSION,
            "profile": "ci",
            "source_commit": "1" * 40,
            "summary": {"green": 1, "rows": 1, "red": 0, "unknown": 0, "skipped": 0, "stale": 0, "not-run": 0},
        }
        path = FIXTURE / "ci-evidence.json"
        try:
            path.write_text(json.dumps(evidence), encoding="utf-8")
            with self.assertRaises(MODULE.DodError) as caught:
                MODULE.reject_stale_ci(path, head())
            self.assertIn("stale CI evidence", str(caught.exception))
        finally:
            path.unlink(missing_ok=True)


if __name__ == "__main__":
    unittest.main()
