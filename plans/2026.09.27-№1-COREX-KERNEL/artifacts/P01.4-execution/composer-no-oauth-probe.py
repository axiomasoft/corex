from pathlib import Path
import json,os,subprocess,tempfile,hashlib,shutil,yaml
root=Path.cwd(); evidence=root/'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.4-execution'
workflow=yaml.safe_load((root/'.github/workflows/ci.yml').read_text())
run=next(x['run'] for x in workflow['jobs']['tests']['steps'] if x.get('name')=='Stage sealed stancl provider candidates')
filter_run=run.split('python3 scripts/distribution/stage-stancl-providers.py\n',1)[1]
results={'scope':'Isolated checkout; actual staged provider dist; root and access updates with empty COMPOSER_HOME and no COMPOSER_AUTH/GH_TOKEN/GITHUB_TOKEN. This is not hosted secret provisioning or live CI.','commands':[]}
scratch=Path(tempfile.mkdtemp(prefix='corex-p014-composer-')); checkout=scratch/'checkout'; auth=scratch/'composer-home';auth.mkdir()
env=dict(os.environ);env['COMPOSER_HOME']=str(auth);env['COMPOSER_CACHE_DIR']=str(Path.home()/'.cache/composer')
for key in ('COMPOSER_AUTH','GH_TOKEN','GITHUB_TOKEN'):env.pop(key,None)
def command(label,args,*,cwd=checkout,cmd_env=env):
    with (evidence/(label+'.log')).open('w') as log:
        proc=subprocess.run(args,cwd=cwd,env=cmd_env,stdout=log,stderr=subprocess.STDOUT)
    results['commands'].append({'label':label,'command':args,'exit_code':proc.returncode,'log':label+'.log'})
    (evidence/'composer-no-oauth.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
    print(label+': '+str(proc.returncode),flush=True)
    if proc.returncode:raise RuntimeError(label+' failed')
try:
    command('provider-probe-checkout',['git','worktree','add','--detach',str(checkout),'HEAD'],cwd=root)
    command('provider-probe-stage',[str(root/'scripts/php84'),'python3','scripts/distribution/stage-stancl-providers.py'],cmd_env=dict(os.environ))
    command('provider-probe-filter',['bash','-e','-c',filter_run])
    checks={}
    for name in ('composer.json','packages/access/composer.json','packages/tenancy/composer.json'):
        data=json.loads((checkout/name).read_text());repos=data['repositories']
        assert all(repo.get('url')!='https://github.com/axiomasoft/azguard.git' for repo in repos.values())
        assert 'stancl-providers' in repos
        checks[name]={'private_vcs_urls':0,'sealed_provider_repository':True}
    results['manifests']=checks
    command('provider-probe-root-update',[str(root/'scripts/php84'),'composer','update','--prefer-dist','--no-interaction','--no-progress'])
    command('provider-probe-access-update',[str(root/'scripts/php84'),'composer','--working-dir=packages/access','update','--prefer-dist','--no-interaction','--no-progress'])
    results['composer_auth_files']=list(str(p.relative_to(auth)) for p in auth.rglob('auth.json'))
    assert results['composer_auth_files']==[]
    results['result']='PASS'
finally:
    results.setdefault('result','FAIL')
    (evidence/'composer-no-oauth.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
    subprocess.run(['git','worktree','remove','--force',str(checkout)],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    shutil.rmtree(scratch,ignore_errors=True)
