"""D63 namespace reuse must not admit renamed custom-fields symbols."""
import json
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


class AttributeRenameTest(unittest.TestCase):
    def test_only_reclaimed_namespace_tokens_pass(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'scripts').mkdir()
            for name in ('rename-completeness-gate.php', 'rename-completeness.json'):
                shutil.copyfile(ROOT / 'scripts' / name, root / 'scripts' / name)
            subprocess.run(['git', 'init', '-q', str(root)], check=True)
            fixture = root / 'probe.php'
            fixture.write_text('<?php namespace CoreX\\Attributes;')
            subprocess.run(['git', '-C', str(root), 'add', 'probe.php'], check=True)
            manifest = json.loads((root / 'scripts/rename-completeness.json').read_text())
            catalogue = 'CoreX\\CustomFields' + '\\Contracts\\FieldCatalogue'
            old_class = next(row['old'] for row in manifest['tokens']['classes']
                             if row.get('new') == catalogue)
            old_schema = 'CoreX\\CustomFields' + '\\Internal\\Schema'
            old_namespace = next(row['old'] for row in manifest['tokens']['namespaces']
                                 if row.get('new') == old_schema)
            for text, expected in (
                ('namespace CoreX\\Attributes;', 0),
                ('namespace CoreX\\Attributes\\Tooling;', 0),
                ('use ' + old_class + ';', 1),
                ('namespace ' + old_namespace + ';', 1),
                ('use ' + catalogue + ';', 1),
                ('namespace ' + old_schema + ';', 1),
                ('use CoreX\\CustomFields\\Schemas\\Contracts\\FieldCatalogue;', 0),
            ):
                with self.subTest(text=text):
                    fixture.write_text('<?php ' + text)
                    result = subprocess.run(['php', str(root / 'scripts/rename-completeness-gate.php')], capture_output=True)
                    self.assertEqual(expected, result.returncode, result.stderr.decode())
