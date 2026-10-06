from pathlib import Path
import json,re
root=Path.cwd();changes=[]
setpaths={}
for meta in list(Path('packages').glob('*/composer.json'))+[Path('products/flex-crm/composer.json')]:
 pkg=meta.parent;d=json.loads(meta.read_text());migr=pkg/'database/migrations'
 if not migr.is_dir():continue
 if pkg.name=='core':sets=[('tenant','pg'),('tenant-modules','')]
 elif pkg.name=='tenancy':sets=[('root','root'),('tenant','tenant')]
 elif pkg.name=='access':sets=[('tenant','tenant'),('tenant-cloud','tenant-cloud')]
 elif pkg.name=='operations':sets=[('tenant','tenant'),('budget','budget')]
 else:sets=[('tenant','tenant' if (migr/'tenant').is_dir() else 'pg' if (migr/'pg').is_dir() else '')]
 for name,sub in sets:setpaths[(migr/sub).resolve()]=d['name']+':'+name

def replacement(path):
 # Resolve Composer symlinks and take the owning set, not tests' own migrations.
 path=path.resolve()
 if path.name=='create_sys_api_idempotency_table.php' and path.parent.name=='migrations':path=path.parent/'tenant'/path.name
 for directory,id in sorted(setpaths.items(),key=lambda x:len(str(x[0])),reverse=True):
  if path==directory or directory in path.parents:
   suffix=str(path.relative_to(directory)) if path!=directory else ''
   expr="app(\\CoreX\\Database\\Contracts\\SchemaSets::class)->get('"+id+"')->path"
   if suffix:expr+=".'/"+suffix+"'"
   return expr
 return None
paths=list(Path('packages').glob('*/tests/**/*.php'))+list(Path('tests').rglob('*.php'))+list(Path('products').glob('*/tests/**/*.php'))
for p in paths:
 s=p.read_text();orig=s
 pattern=r"(?:__DIR__|dirname\(__DIR__(?:,\s*\d+)?\))\s*\.\s*'([^']*database/migrations[^']*)'"
 for m in list(re.finditer(pattern,s))[::-1]:
  prefix=m[0].split('.',1)[0].strip();parts=re.search(r'dirname\(__DIR__(?:,\s*(\d+))?\)',prefix)
  parent=p.parent.resolve()
  if parts:
   for _ in range(int(parts[1] or 1)):parent=parent.parent
  path=parent/m[1].lstrip('/')
  rep=replacement(path)
  if rep:s=s[:m.start()]+rep+s[m.end():];changes.append({'file':str(p),'before':m[0],'after':rep})
 # const path values may no longer be expressions. Inline those named paths into their callers.
 for m in list(re.finditer(r'^(?:private |public |protected )?const(?: string)? ([A-Z_0-9]+) = (app\([^;]+);\n',s,re.M))[::-1]:
  token,value=m[1],m[2];s=s[:m.start()]+s[m.end():];s=re.sub(r'\b'+token+r'\b',lambda _: '('+value+')',s)
 if s!=orig:p.write_text(s)
Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P08.1-execution/test-path-map.json').write_text(json.dumps(changes,ensure_ascii=False,indent=2)+'\n')
print('Updated',len(changes),'path expressions')
