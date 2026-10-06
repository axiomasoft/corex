"""DATA-003 options resolution must retain foreign, unknown and dynamic write failures."""

import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
OPTIONS = r'''namespace CoreX\Config\Data;
final class CoreOptions {
    public function __construct(public string $settingsTable = 'sys_settings') {}
}'''


class TableWriteOwnershipTest(unittest.TestCase):
    def run_gate(self, body, package="other", extra=None):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "scripts").mkdir()
            shutil.copyfile(ROOT / "scripts/table-write-ownership-gate.php",
                            root / "scripts/gate.php")
            (root / "vendor").symlink_to(ROOT / "vendor", target_is_directory=True)
            (root / "docs/architecture").mkdir(parents=True)
            (root / "docs/architecture/table-ownership.md").write_text(
                "| sys_settings | axiomasoft/corex | tenant | axiomasoft/corex |\n")
            sources = {"core": OPTIONS, package: body} if package != "core" else {"core": OPTIONS + body}
            if extra:
                sources.update(extra)
            for name, source in sources.items():
                path = root / "packages" / name
                (path / "src").mkdir(parents=True)
                (path / "composer.json").write_text(json.dumps({
                    "name": "axiomasoft/corex" if name == "core" else "axiomasoft/corex-" + name}))
                (path / "src/Source.php").write_text("<?php\n" + source)
            result = subprocess.run([str(ROOT / "scripts/php84"), "php", str(root / "scripts/gate.php")],
                                    capture_output=True, text=True, check=False)
            return result.returncode, result.stdout + result.stderr

    def test_own_options_and_provider_calls_resolve(self):
        expressions = [r"$this->options->settingsTable", r"app(\CoreX\Config\Data\CoreOptions::class)->settingsTable"]
        for expression in expressions:
            with self.subTest(expression=expression):
                code, output = self.run_gate(r'''namespace Demo;
class Writer {
    public function __construct(private \CoreX\Config\Data\CoreOptions $options) {}
    public function write() { $this->db->table(''' + expression + ''')->delete(); }
}''', package="core")
                self.assertEqual(0, code, output)
                self.assertIn("1 statically resolved writes", output)

    def test_same_options_in_foreign_package_still_fail(self):
        code, output = self.run_gate(r'''namespace Demo;
class Writer {
    public function __construct(private \CoreX\Config\Data\CoreOptions $options) {}
    public function write() { $this->db->table($this->options->settingsTable)->delete(); }
}''')
        self.assertEqual(1, code, output)
        self.assertIn("owned by axiomasoft/corex", output)

    def test_unrelated_options_fqn_stays_dynamic(self):
        code, output = self.run_gate(r'''namespace Demo;
class Writer {
    public function __construct(private \Other\CoreOptions $options) {}
    public function write() { $this->db->table($this->options->settingsTable)->delete(); }
}''')
        self.assertEqual(1, code, output)
        self.assertIn("dynamic table name on write", output)

    def test_unknown_and_dynamic_names_still_fail(self):
        for expression, message in [("'unknown_table'", "missing from catalog"),
                                    ("$this->unknown", "dynamic table name on write")]:
            with self.subTest(expression=expression):
                code, output = self.run_gate("namespace Demo; class Writer { public function write() { "
                                             "$this->db->table(" + expression + ")->delete(); }}")
                self.assertEqual(1, code, output)
                self.assertIn(message, output)

    def test_promoted_self_constant_default_resolves(self):
        code, output = self.run_gate("namespace Demo; class Writer { "
            "private const TABLE = 'sys_settings'; public function __construct(private string $table = self::TABLE) {} "
            "public function write() { $this->db->table($this->table)->delete(); }}", package="core")
        self.assertEqual(0, code, output)

    def test_parameter_and_trait_method_resolve(self):
        body = r'''namespace Demo;
trait Resolves {
    protected function coreOptions(): \CoreX\Config\Data\CoreOptions { throw new \RuntimeException('unused'); }
}
class Model {
    use Resolves;
    public function getTable(): string { return $this->coreOptions()->settingsTable; }
    public function write() { $this->db->table($this->getTable())->delete(); }
}
class Command {
    public function handle(\CoreX\Config\Data\CoreOptions $options) {
        $table = $options->settingsTable;
        $this->db->table($table)->delete();
    }
}'''
        code, output = self.run_gate(body, package="core")
        self.assertEqual(0, code, output)
        self.assertIn("2 statically resolved writes", output)

    def test_unrelated_parameter_stays_dynamic(self):
        code, output = self.run_gate(r'''namespace Demo;
class Command {
    public function handle(\Other\CoreOptions $options) {
        $this->db->table($options->settingsTable)->delete();
    }
}''')
        self.assertEqual(1, code, output)
        self.assertIn("dynamic table name on write", output)

    def test_attribute_tables_promoted_defaults_resolve(self):
        source = r'''namespace CoreX\CustomFields\Data;
class AttributeTables { public function __construct(public string $definitions = 'sys_settings') {} }'''
        body = r'''namespace Demo; class Writer {
public function __construct(private \CoreX\CustomFields\Data\AttributeTables $tables) {}
public function write() { $this->db->table($this->tables->definitions)->delete(); }}'''
        code, output = self.run_gate(body, package="core", extra={"custom-fields": source})
        self.assertEqual(0, code, output)

    def test_literal_attribute_tables_binding_resolves_exact_class(self):
        source = r'''namespace CoreX\CustomFields\Data;
class AttributeTables { public function __construct(public string $documents) {} }
function bind() { return new AttributeTables(documents: 'sys_settings'); }'''
        body = r'''namespace Demo; class Writer {
public function __construct(private \CoreX\CustomFields\Data\AttributeTables $tables) {}
public function write() { $this->db->table($this->tables->documents)->delete(); }}'''
        code, output = self.run_gate(body, package="core", extra={"custom-fields": source})
        self.assertEqual(0, code, output)
        code, output = self.run_gate(body, extra={"custom-fields": source})
        self.assertEqual(1, code, output)
        self.assertIn("owned by axiomasoft/corex", output)

    def test_unrelated_attribute_tables_binding_does_not_resolve(self):
        source = r'''namespace Other;
class AttributeTables { public function __construct(public string $documents) {} }
function bind() { return new AttributeTables(documents: 'sys_settings'); }'''
        body = r'''namespace Demo; class Writer {
public function __construct(private \CoreX\CustomFields\Data\AttributeTables $tables) {}
public function write() { $this->db->table($this->tables->documents)->delete(); }}'''
        code, output = self.run_gate(body, package="core", extra={"custom-fields": source})
        self.assertEqual(1, code, output)
        self.assertIn("dynamic table name on write", output)

    def test_dynamic_attribute_tables_binding_does_not_use_literal_default(self):
        source = r'''namespace CoreX\CustomFields\Data;
class AttributeTables { public function __construct(public string $documents = 'sys_settings') {} }
function bind($table) { return new AttributeTables(documents: $table); }'''
        body = r'''namespace Demo; class Writer {
public function __construct(private \CoreX\CustomFields\Data\AttributeTables $tables) {}
public function write() { $this->db->table($this->tables->documents)->delete(); }}'''
        code, output = self.run_gate(body, package="core", extra={"custom-fields": source})
        self.assertEqual(1, code, output)
        self.assertIn("dynamic table name on write", output)


if __name__ == "__main__":
    unittest.main()
