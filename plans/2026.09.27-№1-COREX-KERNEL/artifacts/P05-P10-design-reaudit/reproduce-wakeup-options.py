#!/usr/bin/env python3
"""Reproduce the audited Options example without changing package sources or DB."""
import base64
from pathlib import Path
import re
import subprocess
import tempfile

plan = Path(__file__).resolve().parents[2]
repo = plan.parents[1]
example = (plan / "artifacts/P05-design/examples.md").read_text()
code = re.search(r"```php\n(.*?)\n```", example, re.S)[1].removeprefix("<?php\n")
encoded = base64.b64encode(code.encode()).decode()
autoload = str(repo / "vendor/autoload.php").replace("'", "\\'")
probe = f"<?php\nrequire '{autoload}';\neval(base64_decode('{encoded}'));\n"
probe += r"""
foreach ([['handlers' => 'broken'], ['handlers' => 42]] as $input) {
    try {
        $result = \CoreX\Wakeup\Internal\WakeupOptions::fromArray($input);
        echo json_encode(['input' => $input, 'outcome' => 'accepted', 'handlers' => $result->handlers]), PHP_EOL;
    } catch (Throwable $e) {
        echo json_encode(['input' => $input, 'outcome' => 'rejected', 'exception' => $e::class]), PHP_EOL;
    }
}
"""
with tempfile.TemporaryDirectory(prefix="corex-options-audit-") as directory:
    path = Path(directory) / "probe.php"
    path.write_text(probe)
    subprocess.run(["php8.4", str(path)], check=True)
