import pathlib,subprocess,tempfile,json,shutil
root=pathlib.Path.cwd();out=pathlib.Path(__file__).resolve().parent
results=[]
with tempfile.TemporaryDirectory(prefix='corex-p093-quality-') as tmp:
 fixture=pathlib.Path(tmp)
 for rel in ['phpstan.neon','phpstan-baseline.neon','phpstan-extensions.neon','scripts/schema-sets.json','scripts/quality-ceilings.json']:
  dst=fixture/rel;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(root/rel,dst)
 for manifest in [*root.glob('packages/*/composer.json'),*root.glob('products/*/composer.json')]:
  base=manifest.parent.relative_to(root);dst=fixture/base;dst.mkdir(parents=True)
  for file in ['composer.json','phpstan.neon','phpstan-baseline.neon']:
   if (root/base/file).exists():shutil.copyfile(root/base/file,dst/file)
  for directory in ['src','database','tests']:
   if (root/base/directory).exists():(dst/directory).symlink_to(root/base/directory,target_is_directory=True)
 for directory in ['tests/Support','tests/Golden']:
  dst=fixture/directory;dst.parent.mkdir(exist_ok=True);dst.symlink_to(root/directory,target_is_directory=True)
 def run(label,expected,needle=''):
  p=subprocess.run(['scripts/php84','php','scripts/quality/release-quality.php','--stage=all','--root='+tmp],text=True,capture_output=True)
  assert p.returncode==expected and needle in p.stdout+p.stderr,(label,p.returncode,p.stdout,p.stderr)
  results.append({'case':label,'exit':p.returncode,'output':p.stdout+p.stderr})
 run('complete-cohort',0)
 path=fixture/'packages/panels/phpstan.neon';before=path.read_text();path.write_text(before.replace('        - database/migrations\n',''));run('omitted-panel-migrations',1,'panels: missing analysis path database/migrations');path.write_text(before)
 path=fixture/'scripts/quality-ceilings.json';before=path.read_text();data=json.loads(before);data['phpstan']['intl']=1;path.write_text(json.dumps(data));run('nonzero-remaining-package-ceiling',1,'intl: ceiling must be zero');path.write_text(before)
 path=fixture/'packages/transfer/phpstan-baseline.neon';before=path.read_text();path.write_text('parameters:\n    ignoreErrors:\n        - identifier: cast.string\n          count: 1\n          path: src/invalid.php\n');run('suppressed-remaining-package-debt',1,'transfer: nonempty baseline');path.write_text(before)
 run('restored-cohort',0)
(out/'negative-controls.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results))
