"""Reclaimed namespaces still reject retired runtime symbols."""
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
            retired_root = 'CoreX' + '\\Attributes'
            fixture.write_text('<?php namespace ' + retired_root + ';')
            subprocess.run(['git', '-C', str(root), 'add', 'probe.php'], check=True)
            manifest = json.loads((root / 'scripts/rename-completeness.json').read_text())
            catalogue = 'CoreX\\CustomFields' + '\\Contracts\\FieldCatalogue'
            old_class = next(row['old'] for row in manifest['tokens']['classes']
                             if row.get('new') == catalogue)
            old_schema = 'CoreX\\CustomFields' + '\\Internal\\Schema'
            old_namespace = next(row['old'] for row in manifest['tokens']['namespaces']
                                 if row.get('new') == old_schema)
            retired_search = 'CoreX\\Search' + '\\Contracts\\SearchProvider'
            retired_query = 'CoreX\\Search' + '\\Data\\SearchQuery'
            bridge = 'CoreX\\TasksWorkflow'
            retired_bridge = bridge + '\\TaskWorkflowIntegration'
            retired_recurring = 'CoreX\\RecurringTasks' + '\\Internal\\TaskWorkflowIntegration'
            for text, expected in (
                ('namespace ' + retired_root + ';', 1),
                ('namespace ' + retired_root + '\\Tooling;', 1),
                ('namespace CoreX\\Foundation\\Tooling;', 0),
                ('use ' + old_class + ';', 1),
                ('namespace ' + old_namespace + ';', 1),
                ('use ' + catalogue + ';', 1),
                ('namespace ' + old_schema + ';', 1),
                ('use CoreX\\CustomFields\\Schemas\\Contracts\\FieldCatalogue;', 0),
                ('use ' + retired_search + ';', 1),
                ('use ' + retired_query + ';', 1),
                ('use CoreX\\Search\\Query\\Contracts\\Search;', 0),
                ('use CoreX\\Search\\Query\\Data\\SearchQuery;', 0),
                ('use ' + retired_bridge + ';', 1),
                ('use ' + retired_recurring + ';', 1),
                ('namespace ' + bridge + '\\Completion\\Internal;', 0),
                ('$name = "' + 'corex/' + 'tasks-workflow";', 1),
                ('$name = "axiomasoft/corex-tasks-workflow";', 0),
            ):
                with self.subTest(text=text):
                    fixture.write_text('<?php ' + text)
                    result = subprocess.run(['php', str(root / 'scripts/rename-completeness-gate.php')], capture_output=True)
                    self.assertEqual(expected, result.returncode, result.stderr.decode())
