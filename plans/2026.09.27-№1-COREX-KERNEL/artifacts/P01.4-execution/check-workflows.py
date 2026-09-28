import pathlib,re,json,subprocess,yaml
p=pathlib.Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.4-execution')
summary={}
for path in pathlib.Path('.github/workflows').glob('*.yml'):
    raw=path.read_text(); data=yaml.safe_load(raw)
    assert data['permissions']=={}
    events=data.get('on',data.get(True))
    assert events and all(event in ['push','pull_request','workflow_dispatch'] for event in events)
    pins=checkout=installs=audits=0
    for name,job in data['jobs'].items():
        assert job['permissions']=={'contents':'read'},(path,name)
        assert 'continue-on-error' not in job
        steps=job['steps']
        for i,step in enumerate(steps):
            if 'uses' in step:
                assert re.fullmatch(r'[^@]+@[0-9a-f]{40}',step['uses']),step
                pins+=1
                if step['uses'].startswith('actions/checkout@'):
                    checkout+=1; assert step['with']['persist-credentials'] is False
            run=step.get('run','')
            assert '${{' not in run,(path,name,run)
            if re.search(r'composer(?: --working-dir=\S+)? (update|install)',run):
                installs+=1
                assert 'composer audit --locked' in steps[i+1].get('run',''),(path,name)
                assert any('stage-stancl-providers.py' in x.get('run','') for x in steps[:i]),(path,name)
            if 'composer audit --locked' in run: audits+=1
    assert len(re.findall(r'@[0-9a-f]{40} # v?[0-9]',raw))==pins
    assert not re.search(r'[\W]8\.3[\W]|Boxed profile',raw)
    summary[path.name]={'jobs':len(data['jobs']),'pins':pins,'checkouts':checkout,'installs':installs,'audits':audits}
ci=yaml.safe_load(pathlib.Path('.github/workflows/ci.yml').read_text())
assert ci['jobs']['tests']['strategy']['matrix']=={'php':['8.4','8.5'],'laravel':['12.*','13.*']}
assert ci['jobs']['tests-pg']['strategy']['matrix']['postgres']==['16','18']
assert ci['jobs']['tests-pg']['services']['postgres']['image']=='postgres:${{ matrix.postgres }}'
for name in ['manifests','golden','ownership','dependencies','zizmor']:
    assert name in ci['jobs']
for path in pathlib.Path('.github').rglob('*.yml'): yaml.safe_load(path.read_text())
(p/'workflow-structure.json').write_text(json.dumps(summary,indent=2)+'\n')
print(json.dumps(summary,indent=2))
