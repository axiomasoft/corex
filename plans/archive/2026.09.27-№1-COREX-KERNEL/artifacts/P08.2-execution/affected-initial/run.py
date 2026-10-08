import datetime, hashlib, json, os, pathlib, subprocess, sys
root = pathlib.Path('/home/vostrikov/projects/packages/corex')
base = pathlib.Path('/tmp/corex-p082-affected')
paths = ['packages', 'products', 'tests', 'scripts', 'docs', 'composer.lock']
def fingerprint(untracked=False):
    args = ['git','ls-files','-z']
    if untracked:
        args += ['--others','--exclude-standard']
    args += ['--'] + paths
    files = sorted(subprocess.check_output(args, cwd=root).split(b'\0'))
    digest = hashlib.sha256()
    count = 0
    for raw in files:
        if not raw: continue
        path = root / os.fsdecode(raw)
        digest.update(raw + b'\0')
        if path.is_file():
            digest.update(b'file\0' + path.read_bytes())
        else:
            digest.update(b'deleted\0')
        digest.update(b'\0')
        count += 1
    return {'sha256':digest.hexdigest(),'paths':count}
def env_fingerprint():
    digest = hashlib.sha256()
    files = sorted(list((root/'vendor/orchestra/testbench-core/laravel/config').glob('corex*.php')) + list((root/'vendor/orchestra/testbench-core/laravel/bootstrap/cache').glob('*.php')) + list((root/'vendor/composer').glob('autoload*.php')))
    for path in files:
        digest.update(str(path.relative_to(root)).encode() + b'\0' + path.read_bytes() + b'\0')
    return {'sha256':digest.hexdigest(), 'paths':[str(path.relative_to(root)) for path in files]}
def stamp():
    return {'tracked_candidate':fingerprint(), 'untracked_scope':fingerprint(True), 'environment_artifacts':env_fingerprint()}
def now(): return datetime.datetime.now(datetime.timezone.utc).isoformat()
commands = [
    ['python3','-c',"from pathlib import Path; import shutil; root=Path('/home/vostrikov/projects/packages/corex'); target=root/'vendor/orchestra/testbench-core/laravel/config/corex.php'; backup=Path('/tmp/corex-p082-affected/stale-testbench-corex.php'); shutil.copy2(target,backup); shutil.copy2(root/'packages/core/config/corex.php',target); print(f'Backed up {target} to {backup}; refreshed from packages/core/config/corex.php')"],
    ['scripts/php84','vendor/bin/pest','tests/Feature/ConfigCacheTest.php','--exclude-group=pg','--colors=never'],
    ['scripts/php84','vendor/bin/pest','packages/core/tests/Unit/CoreOptionsTest.php','--exclude-group=pg','--colors=never'],
    ['scripts/php84','vendor/bin/pest','packages/core/tests/Feature/BaseModelIdentityTest.php','--exclude-group=pg','--colors=never'],
]
env = dict(os.environ)
env.update(APP_ENV='testing', DB_CONNECTION='sqlite', DB_DATABASE=':memory:', PGSQL_HOST='127.0.0.1', PGSQL_PORT='1', PGSQL_DATABASE='corex_test', CACHE_STORE='array', CACHE_DRIVER='array', SESSION_DRIVER='array', QUEUE_CONNECTION='sync')
for command in commands:
    counter = 1
    while True:
        directory = base / f'{counter:03d}'
        try:
            directory.mkdir()
            break
        except FileExistsError:
            counter += 1
    metadata = {'command':command, 'cwd':str(root), 'started_at':now(), 'environment':{key:env[key] for key in ['APP_ENV','DB_CONNECTION','DB_DATABASE','PGSQL_HOST','PGSQL_PORT','PGSQL_DATABASE','CACHE_STORE','CACHE_DRIVER','SESSION_DRIVER','QUEUE_CONNECTION']}, 'before':stamp()}
    (directory / 'metadata.json').write_text(json.dumps(metadata,indent=2)+'\n')
    with (directory / 'command.log').open('w') as log:
        result = subprocess.run(command, cwd=root, env=env, stdout=log, stderr=subprocess.STDOUT)
    metadata.update(exit_code=result.returncode, finished_at=now(), after=stamp())
    metadata['tracked_candidate_stable'] = metadata['before']['tracked_candidate'] == metadata['after']['tracked_candidate']
    metadata['untracked_scope_stable'] = metadata['before']['untracked_scope'] == metadata['after']['untracked_scope']
    (directory / 'metadata.json').write_text(json.dumps(metadata,indent=2)+'\n')
    print(json.dumps({'directory':str(directory), 'exit_code':result.returncode, 'tracked_candidate_stable':metadata['tracked_candidate_stable'], 'untracked_scope_stable':metadata['untracked_scope_stable'], 'before':metadata['before'], 'after':metadata['after']}),flush=True)
    print((directory / 'command.log').read_text(),flush=True)
    if command == ['scripts/php84','--check'] and result.returncode:
        sys.exit(result.returncode)
