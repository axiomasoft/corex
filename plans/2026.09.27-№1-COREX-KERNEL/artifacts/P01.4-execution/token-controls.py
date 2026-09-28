import json,os,pathlib,subprocess,tempfile,yaml
root=pathlib.Path.cwd();p=root/'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.4-execution'
steps=[];runs=[]
for workflow in (root/'.github/workflows').glob('*.yml'):
    for job,body in yaml.safe_load(workflow.read_text())['jobs'].items():
        for step in body['steps']:
            if 'stage-stancl-providers.py' in step.get('run','') or ('--all' in step.get('run','') and 'dependency-analyser.sh' in step.get('run','')):
                assert step['env']['GH_TOKEN']=='${{ secrets.AZGUARD_READ_TOKEN }}'
                assert '${GH_TOKEN:?' in step['run']
                steps.append({'workflow':workflow.name,'job':job,'step':step['name']});runs.append(step['run'])
assert len(steps)==27
stage_runs=set(x for x in runs if 'stage-stancl-providers.py' in x)
assert len(stage_runs)==1
run=next(iter(stage_runs))
with tempfile.TemporaryDirectory(prefix='corex-p014-token-') as tmp:
    scratch=pathlib.Path(tmp);bindir=scratch/'bin';bindir.mkdir();marker=scratch/'stage-ran'
    real_python=subprocess.check_output(['which','python3'],text=True).strip()
    (bindir/'python3').write_text('#!/bin/bash\nif [[ "${1:-}" == scripts/distribution/stage-stancl-providers.py ]]; then touch "$P014_STAGE_MARKER"; exit 0; fi\nexec '+real_python+' "$@"\n');(bindir/'python3').chmod(0o755)
    env=dict(os.environ,PATH=str(bindir)+':'+os.environ['PATH'],P014_STAGE_MARKER=str(marker));env.pop('GH_TOKEN',None)
    missing=subprocess.run(['bash','-e','-c',run],cwd=scratch,env=env,capture_output=True,text=True)
    assert missing.returncode==1 and not marker.exists()
    fixture={'repositories':{'sealed':{'type':'composer','url':'file:///sealed'},'old':{'type':'vcs','url':'https://github.com/axiomasoft/azguard.git'},'path':{'type':'path','url':'packages/*'}}}
    for name in ('composer.json','packages/access/composer.json','packages/tenancy/composer.json'):
        target=scratch/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_text(json.dumps(fixture))
    env['GH_TOKEN']='synthetic-control-value'
    present=subprocess.run(['bash','-e','-c',run],cwd=scratch,env=env,capture_output=True,text=True)
    assert present.returncode==0 and marker.exists() and env['GH_TOKEN'] not in present.stdout+present.stderr
    assert all(json.loads((scratch/name).read_text())['repositories']=={'sealed':fixture['repositories']['sealed'],'path':fixture['repositories']['path']} for name in ('composer.json','packages/access/composer.json','packages/tenancy/composer.json'))
    result={'covered_steps':steps,'explicit_staging_step_count':26,'identical_staging_run_blocks':1,'missing_token':{'exit_code':missing.returncode,'stderr':missing.stderr,'staging_ran':False},'present_synthetic_token':{'exit_code':present.returncode,'staging_ran':True,'credential_printed':False,'private_vcs_removed':True,'other_repositories_preserved':True},'provider_access':'not claimed: owner provisions actual token in O-3'}
    (p/'token-precondition.json').write_text(json.dumps(result,indent=2)+'\n')
print('PASS: 27 scoped credentials; 26 identical stage+filter blocks; missing token fails closed; synthetic token is not printed; VCS removal preserves local/path repos.')
