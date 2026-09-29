"""Planted negatives for the feature-module layout gate (PLAN3.D57). Target: scripts/tests/test_module_layout_gate.py."""

from __future__ import annotations

import importlib.util
import json
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = next(p for p in (ROOT / 'scripts/module-layout-gate.py', Path(__file__).with_name('module-layout-gate.py'))
              if p.is_file())
SPEC = importlib.util.spec_from_file_location('module_layout_gate', SCRIPT)
assert SPEC and SPEC.loader
GATE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(GATE)
CONFIG = {'kernel_modules': ['Audit', 'Data'], 'layer_buckets': ['Support', 'Models']}
VALID = {
    'core/src/CoreServiceProvider.php': 'namespace CoreX; final class CoreServiceProvider {}',
    'core/src/Audit/Contracts/AuditLogger.php': 'namespace CoreX\\Audit\\Contracts; interface AuditLogger {}',
    'core/src/Audit/Internal/QueuedLogger.php': 'namespace CoreX\\Audit\\Internal; final class QueuedLogger {}',
    'search/src/SearchServiceProvider.php': 'namespace CoreX\\Search; use CoreX\\Search\\Index\\Internal\\Driver; '
                                            'final class SearchServiceProvider {}',
    'search/src/Data/SearchQuery.php': 'namespace CoreX\\Search\\Data; final readonly class SearchQuery {}',
    'search/src/Internal/Hydrator.php': 'namespace CoreX\\Search\\Internal; final class Hydrator {}',
    'search/src/Index/Contracts/IndexDriver.php': 'namespace CoreX\\Search\\Index\\Contracts; interface IndexDriver {}',
    'search/src/Index/Internal/Driver.php': 'namespace CoreX\\Search\\Index\\Internal; final class Driver {}',
    'search/src/Index/Laravel/IndexCommand.php': 'namespace CoreX\\Search\\Index\\Laravel; '
                                                 'use CoreX\\Search\\Internal\\Hydrator; final class IndexCommand {}',
}
PLANTED = {
    'R0': ('search/src/Data/Wrong.php', 'namespace CoreX\\Search; final class Wrong {}'),
    'R1': ('core/src/Support/Helper.php', 'namespace CoreX\\Support; final class Helper {}'),
    'R2': ('search/src/Index/Deep/Data/X.php', 'namespace CoreX\\Search\\Index\\Deep\\Data; final class X {}'),
    'R3': ('search/src/Index/Compiler/Plan.php', 'namespace CoreX\\Search\\Index\\Compiler; final class Plan {}'),
    'R4': ('search/src/Index/Laravel/Internal/Y.php', 'namespace CoreX\\Search\\Index\\Laravel\\Internal; final class Y {}'),
    'R5': ('search/src/Index/Internal/Uses.php',
           'namespace CoreX\\Search\\Index\\Internal; use CoreX\\Search\\Internal\\Hydrator; final class Uses {}'),
}


def build(tmp: Path, extra: dict[str, str]) -> Path:
    for directory, ns in (('core', 'CoreX\\'), ('search', 'CoreX\\Search\\')):
        (tmp / 'packages' / directory).mkdir(parents=True, exist_ok=True)
        (tmp / 'packages' / directory / 'composer.json').write_text(json.dumps(
            {'name': 'axiomasoft/corex' if directory == 'core' else 'axiomasoft/corex-search',
             'autoload': {'psr-4': {ns: 'src/'}}}))
    for rel, body in {**VALID, **extra}.items():
        path = tmp / 'packages' / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text('<?php\n\ndeclare(strict_types=1);\n\n' + body.replace('; ', ';\n') + '\n')
    return tmp


def rules(tmp: Path) -> list[str]:
    return [v['rule'] for v in GATE.evaluate(GATE.scan(tmp, GATE.load_packages(tmp)), CONFIG)]


class ModuleLayoutGateTest(unittest.TestCase):
    def test_valid_tree_is_clean(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            self.assertEqual([], rules(build(Path(tmp), {})))

    def test_each_rule_catches_its_planted_negative(self) -> None:
        for rule, (rel, body) in PLANTED.items():
            with self.subTest(rule=rule), tempfile.TemporaryDirectory() as tmp:
                self.assertIn(rule, rules(build(Path(tmp), {rel: body})))

    def test_baseline_rejects_new_and_stale_entries(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = build(Path(tmp), {PLANTED['R3'][0]: PLANTED['R3'][1]})
            config, baseline = root / 'layout.json', root / 'baseline.json'
            config.write_text(json.dumps(CONFIG))
            args = ['--root', str(root), '--config', str(config), '--baseline', str(baseline)]
            self.assertEqual(0, GATE.main(args + ['--write-baseline']))
            self.assertEqual(0, GATE.main(args))
            planted = root / 'packages' / PLANTED['R5'][0]
            planted.write_text('<?php\n' + PLANTED['R5'][1].replace('; ', ';\n') + '\n')
            self.assertEqual(1, GATE.main(args))  # new R5 entry
            planted.unlink()
            (root / 'packages' / PLANTED['R3'][0]).unlink()
            self.assertEqual(1, GATE.main(args))  # fixed R3 entry left in the baseline is stale


if __name__ == '__main__':
    unittest.main()
