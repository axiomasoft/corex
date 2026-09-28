import pathlib,subprocess,json
p=pathlib.Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.4-execution')
scratch=pathlib.Path('build/tools/zizmor-controls'); scratch.mkdir(exist_ok=True)
base='''name: Security control
on: push
permissions: {}
jobs:
  check:
    runs-on: ubuntu-latest
    permissions:
      contents: read
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - run: echo safe
'''
cases={'unpinned-uses':base.replace('@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1','@v7.0.1'),
'artipacked':base.replace('@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1','@v4').replace('        with:\n          persist-credentials: false\n',''),
'excessive-permissions':base.replace('permissions: {}\n','').replace('    permissions:\n      contents: read\n',''),
'template-injection':base.replace('echo safe','echo "${{ github.event.head_commit.message }}"')}
results=[]
for name,raw in cases.items():
    path=scratch/(name+'.yml'); path.write_text(raw)
    r=subprocess.run(['build/tools/venv/bin/zizmor','--offline','--format','json',str(path)],capture_output=True,text=True)
    findings=json.loads(r.stdout)
    assert r.returncode!=0 and any(x['ident']==name for x in findings),(name,r.returncode,r.stdout)
    results.append({'violation':name,'exit_code':r.returncode,'detected':[x['ident'] for x in findings]})
path=scratch/'restored.yml'; path.write_text(base)
r=subprocess.run(['build/tools/venv/bin/zizmor','--offline',str(path)],capture_output=True,text=True)
assert r.returncode==0,r.stdout+r.stderr
results.append({'violation':'restored','exit_code':r.returncode})
(p/'zizmor-negative-controls.json').write_text(json.dumps(results,indent=2)+'\n'); print(json.dumps(results,indent=2))
