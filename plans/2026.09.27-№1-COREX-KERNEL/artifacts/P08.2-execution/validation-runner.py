#!/usr/bin/env python3
import argparse, datetime, hashlib, json, os, pathlib, subprocess, time

ROOT = pathlib.Path('/home/vostrikov/projects/packages/corex')
PRODUCT_ROOTS = ('packages/', 'products/', 'tests/', 'scripts/', 'docs/')

def snapshot():
    paths = subprocess.check_output(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], cwd=ROOT).decode().split('\0')
    paths = sorted(set(p for p in paths if p and (p.startswith(PRODUCT_ROOTS) or ('/' not in p and p not in ('AGENTS.md', 'CLAUDE.md')))))
    h = hashlib.sha256()
    files = {}
    for name in paths:
        p = ROOT / name
        if not p.is_file():
            continue
        digest = hashlib.sha256(p.read_bytes()).hexdigest()
        files[name] = digest
        h.update((name + '\0' + digest + '\n').encode())
    envpaths = ['composer.lock', 'vendor/composer/installed.json', 'vendor/orchestra/testbench-core/laravel/config/corex.php', 'vendor/orchestra/testbench-core/laravel/bootstrap/cache/config.php']
    # Standalone environments populated by canonical V8 composer updates.
    for group in ('packages', 'products'):
        for package in sorted((ROOT / group).glob('*')):
            if not (package / 'composer.json').is_file():
                continue
            for relative in ('composer.lock', 'vendor/composer/installed.json',
                             'vendor/orchestra/testbench-core/laravel/config/corex.php',
                             'vendor/orchestra/testbench-core/laravel/bootstrap/cache/config.php'):
                envpaths.append(str((package / relative).relative_to(ROOT)))
    env = {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() if (ROOT / p).is_file() else 'ABSENT' for p in envpaths}
    runtime = json.loads(subprocess.check_output([str(ROOT / 'scripts/php84'), 'php', '-r', "echo json_encode(['binary'=>PHP_BINARY,'version'=>PHP_VERSION,'version_id'=>PHP_VERSION_ID,'extensions'=>get_loaded_extensions(),'redis_version'=>phpversion('redis'),'extension_dir'=>ini_get('extension_dir'),'ini_loaded'=>php_ini_loaded_file(),'ini_scanned'=>php_ini_scanned_files()]);"], cwd=ROOT, text=True))
    runtime['extensions'] = sorted(runtime['extensions'])
    for key, name in (('binary_sha256', runtime['binary']), ('redis_module_sha256', str(pathlib.Path(runtime['extension_dir']) / 'redis.so'))):
        path = pathlib.Path(name)
        runtime[key] = hashlib.sha256(path.read_bytes()).hexdigest() if path.is_file() else 'ABSENT'
    env['php_runtime'] = runtime
    service_witness = pathlib.Path('/tmp/corex-p082-redis-witness.json')
    if service_witness.is_file():
        env['redis_test_service'] = json.loads(service_witness.read_text())
    return {'product_sha256': h.hexdigest(), 'files': files, 'environment': env, 'git_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()}

def main():
    p = argparse.ArgumentParser()
    p.add_argument('--dir', default='/tmp/corex-p082-validation')
    p.add_argument('--id', required=True)
    p.add_argument('--expect', type=int, default=0)
    p.add_argument('--snapshot-only', action='store_true')
    p.add_argument('command', nargs=argparse.REMAINDER)
    a = p.parse_args()
    dest = pathlib.Path(a.dir)
    dest.mkdir(parents=True, exist_ok=True)
    n = len(list(dest.glob('*/result.json'))) + 1
    run = dest / f'{n:03d}-{a.id}'
    run.mkdir(exist_ok=False)
    before = snapshot()
    (run / 'candidate.json').write_text(json.dumps(before, indent=2) + '\n')
    if a.snapshot_only:
        print(json.dumps({'dir': str(run), 'product_sha256': before['product_sha256']}))
        return
    command = a.command[1:] if a.command and a.command[0] == '--' else a.command
    started = datetime.datetime.now(datetime.timezone.utc).isoformat()
    tic = time.monotonic()
    with (run / 'output.log').open('w') as stream:
        r = subprocess.run(command, cwd=ROOT, stdout=stream, stderr=subprocess.STDOUT)
    after = snapshot()
    product_current = before['product_sha256'] == after['product_sha256']
    result = {'id': a.id, 'command': command, 'started_at': started, 'elapsed_seconds': round(time.monotonic()-tic, 3), 'exit': r.returncode, 'expected_exit': a.expect, 'exit_matches_expectation': r.returncode == a.expect, 'product_current': product_current, 'product_sha256': before['product_sha256'], 'after_product_sha256': after['product_sha256'], 'environment_before': before['environment'], 'environment_after': after['environment'], 'candidate_head': before['git_head'], 'log': str(run / 'output.log')}
    (run / 'result.json').write_text(json.dumps(result, indent=2)+'\n')
    print(json.dumps({k:v for k,v in result.items() if not k.startswith('environment')}))
    if r.returncode != a.expect:
        print('\n'.join((run / 'output.log').read_text(errors='replace').splitlines()[-25:]))
    raise SystemExit(0 if r.returncode == a.expect else 1)

if __name__ == '__main__':
    main()
