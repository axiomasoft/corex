"""Exercise real secret detection at the git history/index/worktree boundaries."""

import base64
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]


class SecretScanGateTest(unittest.TestCase):
    def setUp(self):
        binary = shutil.which(os.environ.get('GITLEAKS_BIN', 'gitleaks'))
        if binary is None:
            self.fail('GITLEAKS_BIN must name the pinned gitleaks binary')
        self.env = {**os.environ, 'GITLEAKS_BIN': str(Path(binary).resolve())}
        self.temporary = tempfile.TemporaryDirectory(prefix='corex-secret-gate-test-')
        self.addCleanup(self.temporary.cleanup)
        self.root = Path(self.temporary.name)
        for name in ('.gitleaks.toml', '.gitleaksignore', 'scripts/secret-scan-gate.sh'):
            target = self.root / name
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(ROOT / name, target)
        self.git('init', '-q')
        self.git('config', 'user.email', 'fixture@example.test')
        self.git('config', 'user.name', 'Secret gate fixture')
        self.git('add', '.')
        self.git('commit', '-qm', 'Policy fixture')

    def git(self, *arguments):
        return subprocess.run(['git', *arguments], cwd=self.root, check=True,
                              capture_output=True, text=True)

    def scan(self):
        return subprocess.run(['bash', 'scripts/secret-scan-gate.sh'], cwd=self.root,
                              env=self.env, capture_output=True, text=True)

    def plant(self, path, provider=True):
        # Construct a synthetic token at runtime; never record a usable credential.
        digest = hashlib.sha256(b'corex-secret-scan-negative-probe').digest()
        token = 'pplx-' + hashlib.sha256(digest).hexdigest()[:48] if provider else base64.b64encode(digest).decode()
        target = self.root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text('API_KEY="' + token + '"\n')
        return token

    def assert_refused(self, token):
        result = self.scan()
        self.assertNotEqual(0, result.returncode, result.stdout + result.stderr)
        self.assertNotIn(token, result.stdout + result.stderr)

    def test_clean_and_ignored_files(self):
        (self.root / '.gitignore').write_text('vendor/\n')
        self.plant('vendor/fixture.txt')
        result = self.scan()
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_untracked_provider_secret(self):
        self.assert_refused(self.plant('new-file.txt'))

    def test_generic_secret_in_audit_is_not_allowlisted(self):
        self.assert_refused(self.plant('audits/fixture.txt', provider=False))

    def test_provider_secret_in_audit_is_not_allowlisted(self):
        self.assert_refused(self.plant('audits/fixture.txt'))

    def test_staged_secret(self):
        token = self.plant('staged.txt')
        self.git('add', 'staged.txt')
        self.assert_refused(token)

    def test_unstaged_secret(self):
        (self.root / 'tracked.txt').write_text('clean\n')
        self.git('add', 'tracked.txt')
        self.git('commit', '-qm', 'Tracked fixture')
        self.assert_refused(self.plant('tracked.txt'))

    def test_committed_secret(self):
        token = self.plant('committed.txt')
        self.git('add', 'committed.txt')
        self.git('commit', '-qm', 'Synthetic negative probe')
        self.assert_refused(token)


if __name__ == '__main__':
    unittest.main()
