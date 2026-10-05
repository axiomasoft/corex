#!/usr/bin/env python3
"""Prepare the disposable CI checkout for validation without optional AzGuard."""

import json
import os
from pathlib import Path


def main() -> None:
    if os.environ.get("GITHUB_ACTIONS") != "true":
        raise SystemExit("ci-native-access: run only in a disposable CI checkout")

    root = Path.cwd()
    manifest_path = root / "composer.json"
    manifest = json.loads(manifest_path.read_text())
    for section in ("require", "require-dev"):
        for package in ("axiomasoft/corex-access-azguard", "axioma-studio/azguard-core"):
            manifest.get(section, {}).pop(package, None)
    manifest["repositories"] = [
        repository
        for repository in manifest["repositories"]
        if repository.get("package", {}).get("name") != "axioma-studio/azguard-core"
    ]
    manifest["scripts"]["test:packages"] = manifest["scripts"]["test:packages"].replace(
        " packages/access-azguard", ""
    )
    # Metadata checks reflect bridge options without booting its provider. Source
    # autoloading preserves those checks without installing the optional engine.
    manifest["autoload-dev"]["psr-4"]["CoreX\\AccessAzguard\\"] = "packages/access-azguard/src/"
    manifest_path.write_text(json.dumps(manifest, indent=4, ensure_ascii=False) + "\n")

    # Whole-graph architecture tests resolve upstream types. Their unchanged
    # suite runs in the full bridge job; other architecture tests stay native.
    phpunit_path = root / "phpunit.xml"
    phpunit = phpunit_path.read_text()
    graph_exclusion = "            <exclude>tests/Arch/PackageGraphTest.php</exclude>"
    if graph_exclusion not in phpunit:
        phpunit = phpunit.replace(
            "            <directory>tests/Arch</directory>",
            "            <directory>tests/Arch</directory>\n" + graph_exclusion,
        )
    phpunit_path.write_text(phpunit)

    # The Golden application composes the real upstream and bridge providers.
    # Its bootstrap is checked in the secret-gated full quality lane. All other
    # Golden helpers and their existing baseline entries remain checked here.
    for base, target in (
        ("phpstan.neon", ".ci-native-phpstan.neon"),
        ("phpstan-tests-lint.neon", ".ci-native-tests-lint.neon"),
    ):
        (root / target).write_text(
            f"includes:\n    - {base}\n\nparameters:\n"
            "    excludePaths:\n        analyse:\n"
            "            - tests/Golden/GoldenTestCase.php\n"
        )
    print("ci-native-access: optional AzGuard installation and bootstrap moved to secret-gated jobs")


if __name__ == "__main__":
    main()
