#!/usr/bin/env python3
"""Actual unmodified module-layout gate on temp projected ten Scheduling files. No product mutation."""
from pathlib import Path
import tempfile, json, subprocess, hashlib
root=Path(__file__).resolve().parents[4]
config=json.loads((root/'scripts/module-layout.json').read_text())
candidate=json.loads(json.dumps(config)); candidate['kernel_modules'].insert(candidate['kernel_modules'].index('Settings'),'Scheduling')
checks={}
def check(name,condition):
 checks[name]=bool(condition)
 if not condition: raise AssertionError(name)
with tempfile.TemporaryDirectory(prefix='corex-p07-scheduling-') as temp:
 t=Path(temp);core=t/'packages/core';core.mkdir(parents=True)
 (core/'composer.json').write_text(json.dumps({'name':'axiomasoft/corex','autoload':{'psr-4':{'CoreX\\':'src/'}}}))
 source={'Contracts':['WakeupScheduler','DueWakeupSource','DueWakeupHandler'],'Data':['WakeupRequest','WakeupWindow','WakeupDelivery','WakeupHandlerDefinition','ScheduleResult','CancelResult','SweepResult']}
 for category,names in source.items():
  target=core/f'src/Scheduling/{category}';target.mkdir(parents=True)
  for name in names:
   src=root/f'packages/wakeup/src/{category}/{name}.php'
   text=src.read_text().replace('CoreX\\Wakeup\\Contracts','CoreX\\Scheduling\\Contracts').replace('CoreX\\Wakeup\\Data','CoreX\\Scheduling\\Data')
   (target/f'{name}.php').write_text(text)
 baseline=t/'baseline.json';baseline.write_text(json.dumps({'schema':'module-layout-baseline/1','entries':[]}))
 def run(cfg):
  c=t/'config.json';c.write_text(json.dumps(cfg))
  result=subprocess.run(['python3',str(root/'scripts/module-layout-gate.py'),'--root',str(t),'--config',str(c),'--baseline',str(baseline)],capture_output=True,text=True)
  return {'exit_code':result.returncode,'stdout':result.stdout,'stderr':result.stderr}
 old=run(config);new=run(candidate)
 check('ten_moved_files',len(list((core/'src/Scheduling').rglob('*.php')))==10)
 check('closed_enum_15_to_16_only_scheduling',len(candidate['kernel_modules'])==16 and [x for x in candidate['kernel_modules'] if x!='Scheduling']==config['kernel_modules'])
 check('old_list_rejects_scheduling_R1',old['exit_code']==1 and 'R1' in old['stdout']+old['stderr'])
 check('new_list_accepts_all_ten',new['exit_code']==0)
 unknown=core/'src/UnknownModule/Contracts';unknown.mkdir(parents=True)
 (unknown/'Unknown.php').write_text('<?php\nnamespace CoreX\\UnknownModule\\Contracts;\ninterface Unknown {}\n')
 bad=run(candidate)
 check('unknown_module_still_rejected_R1',bad['exit_code']==1 and 'R1' in bad['stdout']+bad['stderr'])
print(json.dumps({'passed':len(checks),'checks':checks,'gate_sha256':hashlib.sha256((root/'scripts/module-layout-gate.py').read_bytes()).hexdigest(),'old_enum':old,'candidate_enum':new,'unknown_module':bad,'limits':'Exact existing CLI gate, isolated projected files and empty baseline. Does not simulate full repository public API/import transfer; product gates required in P07.5.'},indent=2))
