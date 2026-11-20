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
CONFIG = {'kernel_modules': ['Audit', 'Data', 'Attributes'], 'layer_buckets': ['Support', 'Models']}
VALID = {
    'core/src/CoreServiceProvider.php': 'namespace CoreX; final class CoreServiceProvider {}',
    'core/src/Audit/Contracts/AuditLogger.php': 'namespace CoreX\\Audit\\Contracts; interface AuditLogger {}',
    'core/src/Audit/Internal/QueuedLogger.php': 'namespace CoreX\\Audit\\Internal; final class QueuedLogger {}',
    'search/src/SearchServiceProvider.php': 'namespace CoreX\\Search; use CoreX\\Search\\Index\\Internal\\Driver; '
                                            'final class SearchServiceProvider {}',
    'search/src/Events/RootEvent.php': 'namespace CoreX\\Search\\Events; final readonly class RootEvent {}',
    'search/src/Query/Data/SearchQuery.php': 'namespace CoreX\\Search\\Query\\Data; final readonly class SearchQuery {}',
    'search/src/Internal/Hydrator.php': 'namespace CoreX\\Search\\Internal; final class Hydrator {}',
    'search/src/Index/Contracts/IndexDriver.php': 'namespace CoreX\\Search\\Index\\Contracts; interface IndexDriver {}',
    'search/src/Index/Internal/Driver.php': 'namespace CoreX\\Search\\Index\\Internal; final class Driver {}',
    'search/src/Index/Laravel/IndexCommand.php': 'namespace CoreX\\Search\\Index\\Laravel; '
                                                 'use CoreX\\Search\\Internal\\Hydrator; final class IndexCommand {}',
}
PLANTED = {
    'R0': ('search/src/Query/Data/Wrong.php', 'namespace CoreX\\Search; final class Wrong {}'),
    'R1': ('core/src/Unlisted/Helper.php', 'namespace CoreX\\Unlisted; final class Helper {}'),
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
    def test_attribute_exception_is_exact(self) -> None:
        valid = {f'core/src/Attributes/{name}.php': f'namespace CoreX\\Attributes; final class {name} {{}}'
                 for name in ('Api', 'Spi', 'SpiKind')}
        with tempfile.TemporaryDirectory() as tmp:
            self.assertEqual([], rules(build(Path(tmp), valid)))
        for extra in (
            {'core/src/Attributes/Fourth.php': 'namespace CoreX\\Attributes; final class Fourth {}'},
            {'core/src/Attributes/Api.php': 'namespace CoreX\\Attributes; final class Other {}'},
            {'search/src/Attributes/Api.php': 'namespace CoreX\\Search\\Attributes; final class Api {}'},
            {'core/src/Attributes/Api.php': 'namespace CoreX\\Attributes; final class Api {}\nfinal class Extra {}'},
        ):
            with self.subTest(extra=extra), tempfile.TemporaryDirectory() as tmp:
                self.assertIn('R3', rules(build(Path(tmp), {**valid, **extra})))

    def test_valid_tree_is_clean(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            self.assertEqual([], rules(build(Path(tmp), {})))

    def test_each_rule_catches_its_planted_negative(self) -> None:
        for rule, (rel, body) in PLANTED.items():
            with self.subTest(rule=rule), tempfile.TemporaryDirectory() as tmp:
                self.assertIn(rule, rules(build(Path(tmp), {rel: body})))

    def test_r5_resolves_namespace_aliases_and_relative_names(self) -> None:
        bodies = [
            'namespace CoreX\\Search\\Query\\Data; use CoreX\\Search\\Index; '
            'final class Uses { public function run() { return new Index\\Internal\\Driver(); }}',
            'namespace CoreX\\Search\\Query\\Data; use CoreX\\Search\\Index as Feature; '
            'final class Uses { public function run() { return Feature\\Internal\\Driver::class; }}',
            'namespace CoreX\\Search\\Query\\Data; use CoreX\\Search\\{Index as Feature, Other}; '
            'final class Uses extends Feature\\Internal\\Driver {}',
            'namespace CoreX\\Search\\Query\\Data; use CoreX\\Search\\Other, CoreX\\Search\\Index as Feature; '
            'final class Uses { public Feature\\Internal\\Driver $driver; }',
            'namespace CoreX\\Search; final class Uses { public Index\\Internal\\Driver $driver; }',
            'namespace CoreX\\Search; final class Uses { public namespace\\Index\\Internal\\Driver $driver; }',
            'namespace CoreX\\Search\\Query\\Data; final class Uses { public \\CoreX\\Search\\Index\\Internal\\Driver $driver; }',
        ]
        for body in bodies:
            with self.subTest(body=body), tempfile.TemporaryDirectory() as tmp:
                rel = 'search/src/Query/Data/Uses.php' if 'namespace CoreX\\Search\\Query\\Data;' in body else 'search/src/Uses.php'
                self.assertIn('R5', rules(build(Path(tmp), {rel: body})))

    def test_only_direct_laravel_bridge_is_composition(self) -> None:
        for path, namespace, forbidden in (
            ('Index/Laravel', 'Index\\Laravel', False),
            ('Index/Internal/Laravel', 'Index\\Internal\\Laravel', True),
            ('Index/Data/Laravel', 'Index\\Data\\Laravel', True),
        ):
            with self.subTest(path=path), tempfile.TemporaryDirectory() as tmp:
                body = f'namespace CoreX\\Search\\{namespace}; use CoreX\\Search\\Internal\\Hydrator; final class Uses {{}}'
                self.assertEqual(forbidden, 'R5' in rules(build(Path(tmp), {f'search/src/{path}/Uses.php': body})))

    def test_kernel_root_provider_name_is_closed(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = build(Path(tmp), {'core/src/ExtraServiceProvider.php':
                                    'namespace CoreX; final class ExtraServiceProvider {}'})
            self.assertIn('R1', rules(root))
        with tempfile.TemporaryDirectory() as tmp:
            root = build(Path(tmp), {'core/src/Audit/AuditServiceProvider.php':
                                    'namespace CoreX\\Audit; final class AuditServiceProvider {}'})
            self.assertEqual([], rules(root))

    def test_strings_comments_and_nonclass_imports_are_not_references(self) -> None:
        source = '''<?php
namespace CoreX\\Search\\Query\\Data;
use function CoreX\\Search\\Index\\Internal\\Driver;
use const CoreX\\Search\\Internal\\Hydrator;
final class Uses {
    public const URL = 'https://example.test';
    public const TEXT = 'CoreX\\Search\\Index\\Internal\\Driver';
    // new \\CoreX\\Search\\Index\\Internal\\Driver();
}
'''
        ns, declarations, refs = GATE.parse(source)
        self.assertEqual('CoreX\\Search\\Query\\Data', ns)
        self.assertEqual(['Uses'], declarations)
        self.assertEqual(set(), refs)

    def test_alias_of_own_module_internal_remains_allowed(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = build(Path(tmp), {'search/src/Index/Data/Uses.php':
                'namespace CoreX\\Search\\Index\\Data; use CoreX\\Search\\Index as Feature; '
                'final class Uses { public Feature\\Internal\\Driver $driver; }'})
            self.assertEqual([], rules(root))

    def test_r6_forbids_runtime_references_to_every_dev_bridge(self) -> None:
        for bridge in ('Tooling', 'PHPStan', 'Testing'):
            for body in (
                f'namespace CoreX\\Search\\Internal; use CoreX\\Attributes\\{bridge}\\Helper; final class Uses {{}}',
                f'namespace CoreX\\Search\\Internal; final class Uses {{ public \\CoreX\\Attributes\\{bridge}\\Helper $helper; }}',
                f'namespace CoreX\\Search\\Internal; use CoreX\\Attributes as A; final class Uses extends A\\{bridge}\\Helper {{}}',
                f'namespace CoreX\\Search\\Internal; final class Uses {{ public {bridge}\\Helper $helper; }}',
            ):
                with self.subTest(body=body), tempfile.TemporaryDirectory() as tmp:
                    self.assertIn('R6', rules(build(Path(tmp), {'search/src/Internal/Uses.php': body})))

    def test_r6_allows_dev_to_dev_references(self) -> None:
        for bridge in ('Tooling', 'PHPStan', 'Testing'):
            with self.subTest(bridge=bridge), tempfile.TemporaryDirectory() as tmp:
                root = build(Path(tmp), {f'search/src/{bridge}/Uses.php':
                    f'namespace CoreX\\Search\\{bridge}; use CoreX\\Attributes\\Tooling\\Helper; final class Uses {{}}'})
                self.assertEqual([], rules(root))

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
