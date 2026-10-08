#!/usr/bin/env python3
"""Serial P07.4 examples §11 validation; OUTPUT_DIR/CANDIDATE required.
No repository copy. Restores exact manifest/report bytes and PHP shim after each run.
"""
from __future__ import annotations
import hashlib
import json
import os
from pathlib import Path
import re
import signal
import stat
import subprocess
import sys
from datetime import datetime, timezone

ROOT = Path.cwd().resolve()
ACCESS = ROOT / 'packages/access/composer.json'
BRIDGE = ROOT / 'packages/access-azguard/composer.json'
REPORT = ROOT / 'artifacts/P12-package-graph/report.json'
SHIM = ROOT / 'build/php84/bin/php'
WATCHED = [ACCESS, BRIDGE, REPORT, SHIM]


def snapshot(path):
    if path.is_symlink():
        return {'kind': 'link', 'target': os.readlink(path)}
    if not path.exists():
        return {'kind': 'absent'}
    info = path.stat()
    if not stat.S_ISREG(info.st_mode):
        raise RuntimeError(f'Unexpected non-file: {path}')
    return {'kind': 'file', 'bytes': path.read_bytes(), 'mode': stat.S_IMODE(info.st_mode)}


def restore(path, saved):
    if snapshot(path) == saved:
        return
    if path.is_symlink() or path.exists():
        path.unlink()
    if saved['kind'] == 'link':
        path.symlink_to(saved['target'])
    elif saved['kind'] == 'file':
        path.write_bytes(saved['bytes'])
        path.chmod(saved['mode'])


def digest(saved):
    if saved['kind'] == 'file':
        return hashlib.sha256(saved['bytes']).hexdigest()
    if saved['kind'] == 'link':
        return 'symlink:' + saved['target']
    return 'absent'


def interrupted(signum, frame):
    raise InterruptedError(f'interrupted by signal {signum}')


def main():
    candidate = os.environ.get('CANDIDATE', '')
    if not re.fullmatch(r'[0-9a-f]{40}', candidate):
        raise RuntimeError('CANDIDATE must be the full current commit SHA')
    head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip()
    if head != candidate:
        raise RuntimeError(f'CANDIDATE {candidate} differs from HEAD {head}')
    value = os.environ.get('OUTPUT_DIR')
    if not value:
        raise RuntimeError('OUTPUT_DIR is required')
    output = Path(value).resolve()
    if any(output == p.parent for p in WATCHED):
        raise RuntimeError('OUTPUT_DIR must not be a watched file directory')
    names = ['01-bridge-unreleased-graph', '02-baseline-manifests',
             '03-mystery-graph', '04-mystery-manifests', '05-foreign-unreleased-manifests']
    reserved = [output / (name + '.log') for name in names] + [output / 'negative-summary.json']
    if any(p.exists() for p in reserved):
        raise RuntimeError('Refusing to overwrite existing logs/summary; use a fresh OUTPUT_DIR')
    if (ROOT / 'scripts/.plan-close-deferred').exists():
        raise RuntimeError('scripts/.plan-close-deferred would skip the graph command')
    # Both commands can write in these directories. Requiring them to exist avoids
    # introducing repository directories during validation.
    if not SHIM.parent.is_dir() or not REPORT.parent.is_dir():
        raise RuntimeError('Existing build/php84/bin and artifacts/P12-package-graph directories required')
    bridge = json.loads(BRIDGE.read_bytes())
    if bridge['name'] != 'axiomasoft/corex-access-azguard' or bridge['extra']['corex']['stability'] != 'unreleased':
        raise RuntimeError('Bridge must already declare its expected name and unreleased stability')
    original = {p: snapshot(p) for p in WATCHED}
    output.mkdir(parents=True, exist_ok=True)
    results = []
    summary = {'candidate': candidate, 'norm': 'P07.4 Validation / P07-design/examples.md §11',
               'preimage': {str(p.relative_to(ROOT)): digest(s) for p, s in original.items()},
               'runs': results, 'restored': False, 'passed': False}

    def restore_all():
        for p, saved in original.items():
            restore(p, saved)
        if any(snapshot(p) != saved for p, saved in original.items()):
            raise RuntimeError('Exact restoration verification failed')

    def run(name, gate, mutation=None, expected=None):
        cmd = ['scripts/php84', 'composer', gate]
        logfile = output / (name + '.log')
        item = {'name': name, 'candidate': candidate, 'command': cmd,
                'log': logfile.name, 'mutation': mutation, 'passed': False}
        results.append(item)
        try:
            if mutation is not None:
                data = json.loads(original[ACCESS]['bytes'])
                data['extra']['corex']['stability'] = mutation
                ACCESS.write_text(json.dumps(data, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
            with logfile.open('xb') as log:
                log.write((f'candidate={candidate}\ncommand={" ".join(cmd)}\n'
                           f'mutation={mutation or "none; bridge already unreleased"}\n'
                           f'started={datetime.now(timezone.utc).isoformat()}\n').encode())
                log.flush()
                process = subprocess.Popen(cmd, cwd=ROOT, stdout=subprocess.PIPE,
                                           stderr=subprocess.STDOUT, start_new_session=True)
                try:
                    raw, _ = process.communicate()
                except BaseException:
                    os.killpg(process.pid, signal.SIGTERM)
                    try:
                        raw, _ = process.communicate(timeout=5)
                    except subprocess.TimeoutExpired:
                        os.killpg(process.pid, signal.SIGKILL)
                        raw, _ = process.communicate()
                    log.write(raw)
                    log.write(f'\nexit={process.returncode}\ninterrupted=true\n'.encode())
                    raise
                log.write(raw)
                log.write(f'\nexit={process.returncode}\n'.encode())
                text = raw.decode('utf-8', errors='replace')
                item['exit'] = process.returncode
                if expected is None:
                    if process.returncode != 0:
                        raise RuntimeError(f'{name}: positive command failed ({process.returncode})')
                    if gate == 'package-graph-gate':
                        if 'package-graph-gate: OK' not in text:
                            raise RuntimeError(f'{name}: graph did not execute successfully')
                        report = json.loads(REPORT.read_bytes())
                        if report.get('violations') != [] or report.get('summary', {}).get('violation_count') != 0:
                            raise RuntimeError(f'{name}: graph report contains violations')
                        item['report_sha256'] = hashlib.sha256(REPORT.read_bytes()).hexdigest()
                    elif not re.search(r'^manifests OK: \d+ roots$', text, flags=re.M):
                        raise RuntimeError(f'{name}: missing clean manifest summary')
                else:
                    expected_exit, diagnostic = expected
                    if process.returncode != expected_exit or diagnostic not in text:
                        raise RuntimeError(f'{name}: expected exit {expected_exit} and diagnostic {diagnostic!r}')
                    if gate == 'manifests:check':
                        violations = re.findall(r'^[^\n]+: (?:golden-closure|php|stability|branch-alias|mono-constraint|illuminate|stack|prerelease|closure|path-repo): [^\n]+$', text, flags=re.M)
                        if violations != [diagnostic] or 'manifests FAIL: 1 violations' not in text:
                            raise RuntimeError(f'{name}: negative contains unrelated manifest violations')
                    else:
                        diagnostics = re.findall(r'^package-graph-gate: [^\n]+$', text, flags=re.M)
                        if diagnostics != [diagnostic]:
                            raise RuntimeError(f'{name}: negative contains unrelated graph diagnostics')
                    item['expected_diagnostic'] = diagnostic
                item['passed'] = True
                log.write(b'verified=true\n')
        finally:
            restore_all()
            item['restored'] = True

    try:
        run(names[0], 'package-graph-gate')
        run(names[1], 'manifests:check')
        run(names[2], 'package-graph-gate', 'mystery',
            (2, 'package-graph-gate: invalid extra.corex.stability in packages/access/composer.json'))
        run(names[3], 'manifests:check', 'mystery',
            (1, "access: stability: unknown extra.corex.stability='mystery'"))
        run(names[4], 'manifests:check', 'unreleased',
            (1, "access: stability: stability=unreleased is reserved for ['axiomasoft/corex-access-azguard']"))
        summary['passed'] = True
    except BaseException as error:
        summary['error'] = f'{type(error).__name__}: {error}'
        raise
    finally:
        restore_all()
        summary['restored'] = True
        summary['postimage'] = {str(p.relative_to(ROOT)): digest(snapshot(p)) for p in WATCHED}
        (output / 'negative-summary.json').write_text(json.dumps(summary, indent=2) + '\n', encoding='utf-8')
    print(f'P07.4 stability carriers PASS; exact restoration verified; candidate={candidate}')
    print(output / 'negative-summary.json')


if __name__ == '__main__':
    signal.signal(signal.SIGTERM, interrupted)
    signal.signal(signal.SIGHUP, interrupted)
    try:
        main()
    except (Exception, KeyboardInterrupt) as error:
        print(f'P07.4 stability carriers FAILED: {error}', file=sys.stderr)
        sys.exit(1)
